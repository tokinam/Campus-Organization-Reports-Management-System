<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        if (! $request->user()->sendEmailVerificationNotification()) {
            return back()->withErrors(['email' => 'We could not send a confirmation link right now. Please check your inbox or try again later.']);
        }

        return back()->with('status', 'verification-link-sent');
    }
}
