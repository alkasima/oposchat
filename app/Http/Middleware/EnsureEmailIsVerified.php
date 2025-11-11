<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $redirectToRoute = null): Response
    {
        if (!$request->user() || !$request->user()->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                abort(403, 'Tu correo electrónico debe ser verificado para acceder a esta función.');
            }

            if ($request->user() && $request->user()->hasVerifiedEmail()) {
                return $next($request);
            }

            return Redirect::route($redirectToRoute ?: 'email.verify.error')
                ->with('error', 'Debes verificar tu correo electrónico para acceder a esta función.');
        }

        return $next($request);
    }
}