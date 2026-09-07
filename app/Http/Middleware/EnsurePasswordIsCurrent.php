<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->must_change_password) {
            if ($request->routeIs('password.force.*') || $request->routeIs('logout')) {
                return $next($request);
            }

            return redirect()->route('password.force.edit');
        }

        return $next($request);
    }
}
