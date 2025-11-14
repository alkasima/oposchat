<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailVerificationController extends Controller
{
    /**
     * Verify email address with token
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $token = $request->token;

        // Find user by verification token
        $user = User::where('email_verification_token', $token)->first();

        if (!$user) {
            return redirect()->route('email.verify.error')
                ->with('error', 'Este enlace ha caducado o no es válido');
        }

        // Check if token is expired (24 hours)
        
        if ($user->verification_email_sent_at && 
            
            $user->verification_email_sent_at->addHours(24)->isPast()) {
            return redirect()->route('email.verify.error')
                ->with('error', 'Este enlace ya no es válido o ha caducado. Por favor, solicita un nuevo enlace de verificación.');
        }

        // Verify the email
        if ($user->verifyEmail($token)) {
            return redirect()->route('email.verify.success');
        }

        return redirect()->route('email.verify.error')
            ->with('error', 'Este enlace ha caducado o no es válido');
    }

    /**
     * Show email verification success page
     */
    public function success()
    {
        return view('auth.email-verified');
    }

    /**
     * Show email verification error page
     */
    public function error(Request $request)
    {
        $error = $request->session()->get('error', 'Este enlace ha caducado o no es válido');
        
        return view('auth.email-verification-error', [
            'error' => $error
        ]);
    }

    /**
     * Resend verification email
     */
    public function resend(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;

        // Rate limiting: Max 5 attempts per hour
        $key = 'email-verification-resend:' . $email;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => ['Demasiados intentos. Intenta nuevamente en una hora.'],
            ]);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            // Don't reveal if user exists or not
            return back()->with('success', 'Si el email existe, recibirás un enlace de verificación.');
        }

        if ($user->hasVerifiedEmail()) {
            return back()->with('success', 'Tu email ya está verificado.');
        }

        try {
            $user->resendVerificationEmail();
            
            $verificationUrl = $user->getEmailVerificationUrl();
            
            $html = view('emails.email-verification', [
                'user' => $user,
                'verificationUrl' => $verificationUrl,
            ])->render();

            $payload = [
                'from' => [
                    'email' => config('services.email_api.from_email'),
                    'name' => config('services.email_api.from_name'),
                ],
                'to' => [[
                    'email' => $user->email,
                    'name' => $user->name,
                ]],
                'subject' => 'Confirma tu correo y activa tu cuenta en OposChat',
                'html_part' => $html,
                'text_part_auto' => true,
            ];

            Http::withHeaders([
                'content-type' => 'application/json',
                'x-auth-token' => config('services.email_api.token'),
            ])->post(config('services.email_api.url'), $payload);
            
            RateLimiter::hit($key, 3600); // 1 hour cooldown

            return back()->with('success', 'Enlace de verificación enviado. Revisa tu correo electrónico.');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al enviar el email. Intenta nuevamente.');
        }
    }
}