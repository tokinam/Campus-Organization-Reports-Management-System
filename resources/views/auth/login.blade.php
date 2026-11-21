<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - Login</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Login CSS -->
        <link rel="stylesheet" href="{{ asset('css/login.css') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="login-page">
            <div class="login-card">
                <div class="login-panel login-panel-left">
                </div>

                <div class="login-panel login-panel-right"
                     x-data="loginLockoutCountdown({{ (int) $lockoutExpiresAt }}, {{ (int) $serverNow }}, @js($lockedEmail), @js($loginEmail))"
                     x-init="start()">
                    @php($showLoginError = $errors->any() || $loginLockoutActive)
                    @php($loginErrorMessage = $errors->first())
                    @php($termEndedLogin = $errors->first('email') === \App\Models\User::TERM_ENDED_MESSAGE || request()->boolean('term_ended'))
                    @php($accountRestrictionLogin = in_array($loginErrorMessage, [
                        'This account has been archived.',
                        'This account is inactive. Please contact the OSDW office.',
                        'This organization account has been deleted and can no longer log in.',
                        'Please sign in with a student organization account.',
                    ], true))
                    @if($termEndedLogin)
                        @php($termEndedParts = explode('For assistance or inquiries, please contact us at ', \App\Models\User::TERM_ENDED_MESSAGE, 2))
                    @endif
                    <a href="{{ route('welcome') }}" class="login-back-link">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Back
                    </a>

                    <div class="login-header">
                        <h1 class="login-heading">Welcome <span style="color: #000 !important">Back</span></h1>
                        <p class="login-subtext">Sign in to your account.</p>
                    </div>

                    @if ((int) $lockoutExpiresAt > (int) $serverNow && filled($lockedEmail))
                        <div class="login-notice login-notice--lockout" role="alert" x-show="lockoutActive" x-transition.opacity.duration.150ms x-cloak>
                            <svg class="login-notice__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M12 6v6l4 2"></path>
                            </svg>
                            <div class="login-notice__content">
                                <div class="login-notice__title-row">
                                    <strong>Login Temporarily Restricted</strong>
                                    <span class="login-notice__countdown" aria-live="polite" x-text="formattedTime">{{ sprintf('%02d:%02d', intdiv(max(0, (int) $lockoutExpiresAt - (int) $serverNow), 60), max(0, (int) $lockoutExpiresAt - (int) $serverNow) % 60) }}</span>
                                </div>
                                <p>Too many unsuccessful login attempts. Please wait before trying again.</p>
                            </div>
                        </div>
                        <div class="login-notice login-notice--success" role="status" x-show="lockoutExpired" x-transition.opacity.duration.150ms x-cloak>
                            <svg class="login-notice__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 6 9 17l-5-5"></path>
                            </svg>
                            <div class="login-notice__content">
                                <strong>You can try again</strong>
                                <p>The temporary restriction has ended. You may sign in now.</p>
                            </div>
                        </div>
                    @endif

                    @if (! $loginLockoutActive && ($termEndedLogin || $accountRestrictionLogin))
                        <div class="login-notice login-notice--warning" role="alert">
                            <svg class="login-notice__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"></path>
                                <path d="M12 8v4"></path>
                                <path d="M12 16h.01"></path>
                            </svg>
                            <div class="login-notice__content">
                                <strong>{{ $termEndedLogin ? 'Account access expired' : 'Account access restricted' }}</strong>
                                @if ($termEndedLogin)
                                    <p>{{ $termEndedParts[0] }}</p>
                                    <p class="login-notice__detail">For assistance or inquiries, please contact us at {{ $termEndedParts[1] ?? '' }}</p>
                                @else
                                    <p>{{ $loginErrorMessage }}</p>
                                @endif
                            </div>
                        </div>
                    @elseif ($errors->any() && ! $loginLockoutActive)
                        <div class="login-notice login-notice--error" role="alert">
                            <svg class="login-notice__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="m15 9-6 6"></path>
                                <path d="m9 9 6 6"></path>
                            </svg>
                            <div class="login-notice__content">
                                <strong>Unable to sign in</strong>
                                <ul class="login-notice__messages">
                                    @foreach ($errors->all() as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    @if (session('status'))
                        @php($verificationEmailSent = session('status') === 'identity-verification-sent')
                        @php($verificationEmailUnavailable = session('status') === 'identity-verification-throttled')
                        <div class="login-notice {{ $verificationEmailSent || $verificationEmailUnavailable ? 'login-notice--info' : 'login-notice--success' }}" role="status">
                            <svg class="login-notice__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                @if ($verificationEmailSent || $verificationEmailUnavailable)
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <path d="M12 16v-4"></path>
                                    <path d="M12 8h.01"></path>
                                @else
                                    <path d="M20 6 9 17l-5-5"></path>
                                @endif
                            </svg>
                            <div class="login-notice__content">
                            @if (session('status') === 'identity-verification-sent')
                                    <strong>Check your email</strong>
                                    <p>We sent a confirmation link. Please confirm your identity to continue.</p>
                            @elseif (session('status') === 'identity-verification-throttled')
                                    <strong>Email confirmation unavailable</strong>
                                    <p>We could not send a confirmation link right now. Check your inbox or try again later.</p>
                            @else
                                    <strong>Success</strong>
                                    <p>{{ session('status') }}</p>
                            @endif
                            </div>
                        </div>
                    @endif
                    @if (session('verification_user_id'))
                        <form method="POST" action="{{ URL::temporarySignedRoute('verification.resend', now()->addMinutes(60), ['id' => session('verification_user_id')]) }}" class="mb-4 text-center">
                            @csrf
                            <input type="hidden" name="email" value="{{ session('verification_email', old('email')) }}">
                            <button type="submit" class="font-semibold text-orange-700 underline hover:text-orange-900">Resend verification email</button>
                        </form>
                    @endif
                    @if (session('verification-status'))
                        <div class="login-notice login-notice--info" role="status">
                            <svg class="login-notice__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M12 16v-4"></path>
                                <path d="M12 8h.01"></path>
                            </svg>
                            <div class="login-notice__content"><p>{{ session('verification-status') }}</p></div>
                        </div>
                    @endif
                    @if (session('verification-error'))
                        <div class="login-notice login-notice--warning" role="alert">
                            <svg class="login-notice__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"></path>
                                <path d="M12 8v4"></path>
                                <path d="M12 16h.01"></path>
                            </svg>
                            <div class="login-notice__content"><p>{{ session('verification-error') }}</p></div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="login-form">
                        @csrf

                        <div>
                            <div class="form-group student-group">
                                <label for="email" class="sr-only">Email address</label>
                                <div class="input-with-icon">
                                    <svg class="field-icon" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg>
                                    <input
                                        id="email"
                                        type="email"
                                        name="email"
                                        value="{{ $loginEmail }}"
                                        x-on:input="updateEmail($event.target.value)"
                                        required
                                        autofocus
                                        autocomplete="username"
                                        placeholder="Email address"
                                        class="form-input {{ $errors->has('email') && ! $termEndedLogin ? 'form-input--error' : '' }}"
                                    />
                                </div>
                                @if (! $showLoginError && ! $termEndedLogin)
                                    @error('email')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                @endif
                            </div>

                            <div class="form-group student-group">
                                <label for="password" class="sr-only">Password</label>
                                <div class="password-input-wrapper">
                                    <svg class="field-icon" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path><path d="M12 14v3"></path></svg>
                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        required
                                        autocomplete="current-password"
                                        placeholder="Password"
                                        class="form-input {{ $errors->has('password') ? 'form-input--error' : '' }}"
                                    />
                                    <button type="button" class="password-toggle" onclick="togglePassword(this)" aria-label="Toggle password visibility">
                                        <svg class="eye-icon eye-open" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                        <svg class="eye-icon eye-closed" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;">
                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                            <line x1="1" y1="1" x2="23" y2="23"/>
                                        </svg>
                                    </button>
                                </div>
                                @if (! $showLoginError)
                                    @error('password')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                @endif
                            </div>

                            <div class="form-actions student-group">
                                 <div class="remember-me-wrapper">
                                     <input id="remember_me" type="checkbox" name="remember" class="form-checkbox">
                                     <label for="remember_me" class="remember-label">Remember me</label>
                                 </div>

                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="forgot-password-link student">Forgot password?</a>
                                @endif
                            </div>

                            <div class="captcha-block">
                                <div class="captcha-prompt">
                                    <span id="loginCaptchaCode" class="captcha-code" aria-label="Captcha code">{{ $captcha }}</span>
                                    <button type="button" id="refreshCaptcha" class="captcha-refresh">Refresh code</button>
                                </div>
                                <label for="captcha" class="sr-only">Captcha code</label>
                                <div class="input-with-icon">
                                    <svg class="field-icon" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 20 6v5c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6z"></path><path d="m8.5 12 2.2 2.2 4.8-4.8"></path></svg>
                                    <input
                                        id="captcha"
                                        type="text"
                                        name="captcha"
                                        required
                                        autocomplete="off"
                                        autocapitalize="characters"
                                        placeholder="Enter the code above"
                                        class="form-input captcha-input {{ $errors->has('captcha') ? 'form-input--error' : '' }}"
                                    />
                                </div>
                                @if (! $showLoginError)
                                    @error('captcha')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                @endif
                            </div>

                            <button type="submit" class="login-button student-submit-button" @disabled($loginLockoutActive) x-bind:disabled="lockoutActive">
                                <span x-text="lockoutActive ? `Try again in ${formattedTime}` : 'Sign In'">Sign In</span>
                                <svg x-show="!lockoutActive" class="button-icon" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                    <polyline points="12 5 19 12 12 19"></polyline>
                                </svg>
                            </button>
                        </div>

                    </form>

                    <div class="login-help">
                        <span>Having trouble?</span> <a href="{{ route('password.request') }}">Reset your password</a>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('loginLockoutCountdown', (expiresAt, serverNow, lockedEmail, currentEmail) => {
                    const serverOffset = (Number(serverNow) * 1000) - Date.now();

                    return {
                        expiresAt: Number(expiresAt),
                        lockedEmail: String(lockedEmail || '').trim().toLowerCase(),
                        email: String(currentEmail || '').trim().toLowerCase(),
                        remaining: Math.max(0, Number(expiresAt) - Number(serverNow)),
                        timer: null,
                        get lockoutActive() {
                            return this.remaining > 0 && this.email !== '' && this.email === this.lockedEmail;
                        },
                        get lockoutExpired() {
                            return this.expiresAt > 0 && this.remaining === 0 && this.email !== '' && this.email === this.lockedEmail;
                        },
                        get formattedTime() {
                            const minutes = Math.floor(this.remaining / 60);
                            const seconds = this.remaining % 60;
                            return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                        },
                        updateEmail(value) {
                            this.email = String(value || '').trim().toLowerCase();
                        },
                        start() {
                            if (this.expiresAt <= 0) return;

                            const updateRemaining = () => {
                                const serverTime = (Date.now() + serverOffset) / 1000;
                                this.remaining = Math.max(0, Math.ceil(this.expiresAt - serverTime));
                                if (this.remaining === 0) {
                                    window.clearInterval(this.timer);
                                    this.timer = null;
                                }
                            };

                            updateRemaining();
                            if (this.remaining > 0) {
                                this.timer = window.setInterval(updateRemaining, 250);
                            }
                        },
                    };
                });
            });

            function togglePassword(button) {
                const wrapper = button.closest('.password-input-wrapper');
                if (!wrapper) {
                    return;
                }
                const passwordInput = wrapper.querySelector('input[type="password"], input[type="text"]');
                const eyeOpen = wrapper.querySelector('.eye-open');
                const eyeClosed = wrapper.querySelector('.eye-closed');

                if (!passwordInput) {
                    return;
                }

                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    if (eyeOpen) eyeOpen.style.display = 'none';
                    if (eyeClosed) eyeClosed.style.display = 'block';
                } else {
                    passwordInput.type = 'password';
                    if (eyeOpen) eyeOpen.style.display = 'block';
                    if (eyeClosed) eyeClosed.style.display = 'none';
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.animation = 'slideIn 0.4s ease-out';
                });

                const refreshButton = document.getElementById('refreshCaptcha');
                const captchaCode = document.getElementById('loginCaptchaCode');
                const captchaInput = document.getElementById('captcha');
                refreshButton?.addEventListener('click', async function() {
                    refreshButton.disabled = true;
                    try {
                        const response = await fetch("{{ route('login.captcha.refresh') }}", {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) throw new Error('Captcha refresh failed.');
                        const data = await response.json();
                        captchaCode.textContent = data.captcha;
                        captchaInput.value = '';
                        captchaInput.focus();
                    } finally {
                        refreshButton.disabled = false;
                    }
                });
            });
        </script>
    </body>
</html>
