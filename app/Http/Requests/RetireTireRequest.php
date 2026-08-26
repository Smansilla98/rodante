<?php

namespace App\Http\Requests;

use App\Models\Tire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RetireTireRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tire $tire */
        $tire = $this->route('tire');
        if (! Gate::forUser($this->user())->check('view', $tire)) {
            abort(404);
        }

        return Gate::forUser($this->user())->allows('retire', $tire);
    }

    public function rules(): array
    {
        return [
            'reason_id' => 'required|exists:movement_reasons,id',
            'notes' => 'nullable|string',
            'photos' => 'nullable|array|max:6',
            'photos.*' => 'file|mimetypes:image/jpeg,image/png,image/webp|max:5120',
        ];
    }
}
