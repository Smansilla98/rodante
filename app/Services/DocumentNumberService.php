<?php

namespace App\Services;

use App\Models\DocumentCounter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function next(int $companyId, string $document, string $prefix): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($companyId, $document, $prefix) {
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

                    return $prefix.str_pad((string) $row->value, 5, '0', STR_PAD_LEFT);
                });
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new \RuntimeException('No se pudo asignar el número de documento. Reintentá.');
    }
}
