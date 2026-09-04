<?php

namespace App\Http\Requests;

use App\Models\Tire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReturnTireToStockRequest extends FormRequest
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
            'notes' => 'nullable|string|max:255',
            'as_recap' => 'sometimes|boolean',
        ];
    }
}
