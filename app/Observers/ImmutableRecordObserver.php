<?php

namespace App\Observers;

use App\Exceptions\DomainException;
use Illuminate\Database\Eloquent\Model;

class ImmutableRecordObserver
{
    public function updating(Model $model): void
    {
        throw new DomainException('El historial no se modifica. Si hay un error, registrá un evento de corrección.');
    }

    public function deleting(Model $model): void
    {
        throw new DomainException('El historial no se borra.');
    }
}
