<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        // Only allow login if the email has been verified
        if (!$user || !$user->hasVerifiedEmail()) {
            if ($user) {
                Auth::guard('web')->logout();
            }

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'unverified');
        }

        $request->session()->regenerate();

        // Debug logging
        \Log::info('User logged in', [
            'user_id' => $user->id,
            'remember_token' => $user->remember_token ? 'SET' : 'NOT SET',
            'remember_requested' => $request->boolean('remember'),
        ]);

        // If the intended URL is an API endpoint, ignore it to prevent Inertia expecting a page from JSON
        $intended = url()->previous();
        if ($request->session()->has('url.intended')) {
            $intended = $request->session()->get('url.intended');
        }
        if (is_string($intended) && str_contains(parse_url($intended, PHP_URL_PATH) ?? '', '/api/')) {
            $request->session()->forget('url.intended');
        }

        // Redirect admin users to admin dashboard
        if ($user->is_admin) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        return redirect()->intended(route('home', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
