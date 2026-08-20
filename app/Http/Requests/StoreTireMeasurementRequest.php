<?php

namespace App\Http\Requests;

use App\Models\Tire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreTireMeasurementRequest extends FormRequest
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
            'measured_at' => 'nullable|date',
            'odometer' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'readings' => 'required|array',
            'readings.*.zone_id' => 'required|exists:measurement_zones,id',
            'readings.*.millimeters' => 'required|numeric|min:0|max:40',
        ];
    }
}
