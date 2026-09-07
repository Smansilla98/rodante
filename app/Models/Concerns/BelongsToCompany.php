<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

trait BelongsToCompany
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $model = $builder->getModel();
            if (! self::modelHasCompanyColumn($model)) {
                return;
            }
            $tenant = app(TenantContext::class);
            if ($tenant->bypassed()) {
                return;
            }
            $companyId = $tenant->id();
            if ($companyId === null) {
                $builder->whereRaw('1 = 0');

                return;
            }
            $builder->where($model->qualifyColumn('company_id'), $companyId);
        });

        static::creating(function (Model $model) {
            if (! self::modelHasCompanyColumn($model)) {
                return;
            }
            if ($model->getAttribute('company_id')) {
                return;
            }
            $tenant = app(TenantContext::class);
            if ($tenant->id()) {
                $model->setAttribute('company_id', $tenant->id());

                return;
            }
            if ($userCompany = auth()->user()?->company_id) {
                $model->setAttribute('company_id', (int) $userCompany);

                return;
            }
            throw new \RuntimeException(
                'No hay empresa en contexto. Usá TenantContext::for() en comandos, colas o seeders.'
            );
        });
    }

    public function scopeWithoutTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    private static function modelHasCompanyColumn(Model $model): bool
    {
        try {
            return Schema::hasTable($model->getTable())
                && Schema::hasColumn($model->getTable(), 'company_id');
        } catch (\Throwable) {
            return false;
        }
    }
}
