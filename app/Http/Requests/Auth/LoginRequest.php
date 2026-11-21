<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const LOCKOUT_THRESHOLD = 5;

    private const LOCKOUT_SECONDS_PER_ADDITIONAL_FAILURE = 60;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }

        $this->ensureIsNotRateLimited();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Please enter your password.',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): bool
    {
        $this->ensureIsNotRateLimited();

        $user = User::query()->where('email', $this->input('email'))->first();
        if (! $user) {
            $this->failAuthentication('email', 'No account found with this email.');
        }

        if (! Hash::check($this->input('password'), $user->password)) {
            $this->failAuthentication('password', 'Incorrect password.');
        }

        $termEnded = $user->isTermEnded();
        if ($termEnded) {
            activity('auth')
                ->performedOn($user)
                ->withProperties(['user_id' => $user->id])
                ->log('login blocked: term ended');
        }

        if ($termEnded && $user->officer_status !== 'archived' && $user->archived_at === null) {
            throw ValidationException::withMessages([
                'email' => User::TERM_ENDED_MESSAGE,
            ]);
        }

        if (! $user->isAdmin() && $user->archived_at !== null) {
            throw ValidationException::withMessages([
                'email' => 'This account has been archived.',
            ]);
        }

        if (! $user->isAdmin() && $user->officer_status !== 'active') {
            throw ValidationException::withMessages([
                'email' => 'This account is inactive. Please contact the OSDW office.',
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            $sent = $user->sendEmailVerificationNotification();
            $this->session()->flash('identity-verification-sent', $sent);
            $this->session()->flash('identity-verification-user-id', $user->id);

            return false;
        }

        Cache::forget($this->throttleKey());
        RateLimiter::clear($this->lockoutKey());
        $this->clearLockoutPresentation();
        $user->forceFill(['last_login_at' => now()])->save();
        Auth::login($user, $this->boolean('remember'));

        return true;
    }

    private function failAuthentication(string $field, string $message): never
    {
        $lockoutSeconds = $this->recordFailedAttempt();
        if ($lockoutSeconds !== null) {
            $this->ensureIsNotRateLimited();
        }

        throw ValidationException::withMessages([$field => $message]);
    }

    public function recordFailedAttempt(): ?int
    {
        $attemptsKey = $this->throttleKey();
        Cache::add($attemptsKey, 0);
        $failedAttempts = (int) Cache::increment($attemptsKey);

        if ($failedAttempts < self::LOCKOUT_THRESHOLD) {
            return null;
        }

        $lockoutSeconds = ($failedAttempts - 4) * self::LOCKOUT_SECONDS_PER_ADDITIONAL_FAILURE;
        $lockoutKey = $this->lockoutKey();
        RateLimiter::hit($lockoutKey, $lockoutSeconds);

        $remainingSeconds = RateLimiter::availableIn($lockoutKey);
        $this->rememberLockoutPresentation(now()->timestamp + $remainingSeconds);

        return $remainingSeconds;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $lockoutKey = $this->lockoutKey();
        if (! RateLimiter::tooManyAttempts($lockoutKey, 1)) {
            return;
        }

        $seconds = RateLimiter::availableIn($lockoutKey);
        if ($seconds <= 0) {
            RateLimiter::clear($lockoutKey);

            return;
        }

        event(new Lockout($this));
        $expiresAt = now()->timestamp + $seconds;
        $this->rememberLockoutPresentation($expiresAt);

        $minutes = max(1, (int) ceil($seconds / 60));
        $unit = $minutes === 1 ? 'minute' : 'minutes';
        throw ValidationException::withMessages([
            'email' => "Too many attempts. Try again in {$minutes} {$unit}.",
        ]);
    }

    private function rememberLockoutPresentation(int $expiresAt): void
    {
        $this->session()->put([
            'login_lockout_expires_at' => $expiresAt,
            'login_lockout_email' => $this->normalizedEmail(),
            'login_lockout_ip' => $this->ip(),
        ]);
    }

    private function clearLockoutPresentation(): void
    {
        $this->session()->forget([
            'login_lockout_expires_at',
            'login_lockout_email',
            'login_lockout_ip',
        ]);
    }

    private function lockoutKey(): string
    {
        return $this->throttleKey().':lockout';
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return 'login:'.hash('sha256', $this->normalizedEmail().'|'.$this->ip());
    }

    private function normalizedEmail(): string
    {
        $email = $this->input('email');

        return is_string($email) ? Str::lower(trim($email)) : '';
    }
}
