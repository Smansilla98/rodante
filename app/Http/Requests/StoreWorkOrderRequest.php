<?php

namespace App\Http\Requests;

use App\Enums\WorkOrderType;
use App\Models\Tire;
use App\Support\AccessScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\WorkOrder::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'tire_id' => 'required|exists:tires,id',
            'retread_shop_id' => 'required|exists:retread_shops,id',
            'type' => ['required', Rule::enum(WorkOrderType::class)],
            'notes' => 'nullable|string',
        ];
    }

    protected function passedValidation(): void
    {
        AccessScope::abortUnlessTire($this->user(), (int) $this->input('tire_id'));
    }
}
