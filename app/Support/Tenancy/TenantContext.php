<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use RuntimeException;

class TenantContext
{
    private ?int $companyId = null;

    private bool $bypassed = false;

    public function set(?Company $company): void
    {
        $this->companyId = $company?->id ? (int) $company->id : null;
    }

    public function setId(?int $companyId): void
    {
        $this->companyId = $companyId ? (int) $companyId : null;
    }

    public function id(): ?int
    {
        return $this->companyId;
    }

    public function company(): ?Company
    {
        if ($this->companyId === null) {
            return null;
        }

        return Company::query()->find($this->companyId);
    }

    public function requireId(): int
    {
        if ($this->bypassed) {
            throw new RuntimeException('TenantContext está en bypass: no se puede exigir empresa.');
        }
        if ($this->companyId === null) {
            throw new RuntimeException('No hay empresa en contexto. Usá TenantContext::for() en comandos, colas o seeders.');
        }

        return $this->companyId;
    }

    public function bypass(bool $value = true): void
    {
        $this->bypassed = $value;
    }

    public function bypassed(): bool
    {
        return $this->bypassed;
    }

    public function clear(): void
    {
        $this->companyId = null;
        $this->bypassed = false;
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function for(Company $company, callable $callback): mixed
    {
        $ctx = app(self::class);
        $previousId = $ctx->companyId;
        $previousBypass = $ctx->bypassed;
        $ctx->bypassed = false;
        $ctx->set($company);
        try {
            return $callback();
        } finally {
            $ctx->companyId = $previousId;
            $ctx->bypassed = $previousBypass;
        }
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutTenant(callable $callback): mixed
    {
        $ctx = app(self::class);
        $previous = $ctx->bypassed;
        $ctx->bypassed = true;
        try {
            return $callback();
        } finally {
            $ctx->bypassed = $previous;
        }
    }
}
