<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCapability
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $allowed = match ($capability) {
            'write' => $user->role->canWrite(),
            'couplings' => $user->role->canManageCouplings(),
            'odometer' => $user->role->canValidateOdometer(),
            'retire' => $user->role->canRetireOrRecap(),
            'config' => $user->role->canChangeConfiguration(),
            'abm' => $user->role->canManageAbm(),
            default => false,
        };

        if (! $allowed) {
            abort(403, 'No tiene permiso para esta acción.');
        }

        return $next($request);
    }
}
