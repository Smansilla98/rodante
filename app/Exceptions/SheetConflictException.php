<?php

namespace App\Exceptions;

class SheetConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Otro operario ya cambió esta ubicación. Recargá la planilla.');
    }
}
