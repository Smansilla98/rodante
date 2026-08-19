<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->is_active) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'La cuenta está desactivada.'], 403);
            }
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'username' => 'La cuenta está desactivada.',
            ]);
        }

        return $next($request);
    }
}
