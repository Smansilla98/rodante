<?php

namespace App\Services;

use App\Models\DocumentCounter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function next(int $companyId, string $document, string $prefix): string
    {
        return $prefix.str_pad((string) $this->nextValue($companyId, $document), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Contador entero serializado por empresa+documento (lockForUpdate).
     * Usado para OC y para números individuales de cubierta.
     */
    public function nextValue(int $companyId, string $document): int
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($companyId, $document) {
                    DocumentCounter::query()->insertOrIgnore([
                        'company_id' => $companyId,
                        'document' => $document,
                        'value' => 0,
                    ]);

                    $row = DocumentCounter::query()
                        ->where('company_id', $companyId)
                        ->where('document', $document)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $row->value++;
                    $row->save();

                    return (int) $row->value;
                });
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new \RuntimeException('No se pudo asignar el número de documento. Reintentá.');
    }

    /** Asegura que el contador no quede por debajo de un número ya usado (p. ej. first_number manual). */
    public function ensureAtLeast(int $companyId, string $document, int $minValue): void
    {
        if ($minValue < 1) {
            return;
        }

        DB::transaction(function () use ($companyId, $document, $minValue) {
            DocumentCounter::query()->insertOrIgnore([
                'company_id' => $companyId,
                'document' => $document,
                'value' => 0,
            ]);

            $row = DocumentCounter::query()
                ->where('company_id', $companyId)
                ->where('document', $document)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $row->value < $minValue) {
                $row->value = $minValue;
                $row->save();
            }
        });
    }
}
