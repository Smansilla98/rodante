<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->company_id) {
            $this->tenant->setId((int) $user->company_id);
        } else {
            $this->tenant->clear();
        }

        return $next($request);
    }
}
