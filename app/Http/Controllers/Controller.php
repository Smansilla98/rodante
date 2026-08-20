<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Facades\Gate;

abstract class Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;

    /**
     * Igual que authorize(), pero 404 si falla (tenant / visibilidad).
     * Usar para view; para capacidades (write/retire) preferir authorize() → 403.
     */
    protected function authorizeVisible(string $ability, mixed $arguments = []): void
    {
        if (! Gate::forUser(request()->user())->check($ability, $arguments)) {
            abort(404);
        }
    }
}
