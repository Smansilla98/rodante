<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);
        if (! in_array($user->role, $allowed, true) && $user->role !== UserRole::Administrador) {
            abort(403, 'No tiene permiso para esta acción.');
        }

        return $next($request);
    }
}
