<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\Tire;
use App\Models\TirePhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TirePhotoService
{
    public const DISK = 'local';

    public const KIND_RETIRE = 'RETIRE';

    /**
     * @param  list<UploadedFile>|array<int, mixed>  $files
     */
    public function storeRetirement(Tire $tire, array $files, User $user): void
    {
        $stored = [];

        try {
            foreach ($files as $file) {
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    continue;
                }
                $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
                if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $ext = 'jpg';
                }
                $name = Str::uuid()->toString().'.'.$ext;
                $dir = 'tire-photos/'.$tire->company_id.'/'.$tire->id;
                $path = $file->storeAs($dir, $name, self::DISK);
                if (! $path) {
                    throw new DomainException('No se pudo guardar una foto de la baja.');
                }
                $stored[] = $path;

                TirePhoto::create([
                    'company_id' => $tire->company_id,
                    'tire_id' => $tire->id,
                    'kind' => self::KIND_RETIRE,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType() ?: 'image/jpeg',
                    'size' => $file->getSize() ?: null,
                    'user_id' => $user->id,
                    'captured_at' => now(),
                ]);
            }
        } catch (DomainException $e) {
            $this->forget($stored);
            throw $e;
        } catch (\Throwable $e) {
            $this->forget($stored);
            throw new DomainException('No se pudieron guardar las fotos de baja.');
        }
    }

    public function contents(TirePhoto $photo): string
    {
        if (! Storage::disk(self::DISK)->exists($photo->path)) {
            abort(404);
        }

        return Storage::disk(self::DISK)->get($photo->path);
    }

    /**
     * @param  list<string>  $paths
     */
    private function forget(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
