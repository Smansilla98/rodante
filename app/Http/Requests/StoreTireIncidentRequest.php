<?php

namespace App\Http\Requests;

use App\Enums\IncidentType;
use App\Models\Tire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTireIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tire $tire */
        $tire = $this->route('tire');
        if (! Gate::forUser($this->user())->check('view', $tire)) {
            abort(404);
        }

        return Gate::forUser($this->user())->allows('write', $tire);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(IncidentType::class)],
            'occurred_at' => 'nullable|date',
            'description' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'odometer' => 'nullable|integer|min:0',
        ];
    }
}
