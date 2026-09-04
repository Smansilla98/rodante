<?php

namespace App\Http\Requests;

use App\Enums\WorkOrderType;
use App\Support\AccessScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\WorkOrder::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'tire_id' => 'nullable|exists:tires,id',
            'tire_ids' => 'nullable|array|min:1',
            'tire_ids.*' => 'integer|exists:tires,id',
            'retread_shop_id' => 'required|exists:retread_shops,id',
            'type' => ['required', Rule::enum(WorkOrderType::class)],
            'notes' => 'nullable|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ids = $this->tireIds();
            $type = WorkOrderType::tryFrom((string) $this->input('type'));

            if ($ids === []) {
                $validator->errors()->add('tire_ids', 'Elegí al menos una cubierta.');

                return;
            }

            if ($type === WorkOrderType::Reparacion && count($ids) > 1) {
                $validator->errors()->add('tire_ids', 'La reparación es de una sola cubierta.');
            }

            foreach ($ids as $id) {
                AccessScope::abortUnlessTire($this->user(), $id);
            }
        });
    }

    /**
     * @return list<int>
     */
    public function tireIds(): array
    {
        return collect($this->input('tire_ids', []))
            ->when($this->filled('tire_id'), fn ($c) => $c->push($this->input('tire_id')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
