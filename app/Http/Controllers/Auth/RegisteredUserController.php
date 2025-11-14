<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Http;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Generate email verification token and send email
        $user->generateEmailVerificationToken();
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

        event(new Registered($user)); // keep event for any listeners

        Auth::login($user);

        // Redirect to verification notice page
        return to_route('verification.notice');
    }
}
