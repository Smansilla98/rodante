<?php

namespace App\Http\Requests;

use App\Models\Tire;
use App\Models\TireModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTireRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tire $tire */
        $tire = $this->route('tire');
        if (! Gate::forUser($this->user())->check('view', $tire)) {
            abort(404);
        }

        return Gate::forUser($this->user())->allows('update', $tire);
    }

    public function rules(): array
    {
        /** @var Tire $tire */
        $tire = $this->route('tire');

        return [
            'individual_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('tires', 'individual_number')
                    ->ignore($tire->id)
                    ->where(fn ($q) => $q->where('company_id', $tire->company_id)),
            ],
            'number_reason' => 'nullable|string|max:255',
            'tire_brand_id' => 'required|exists:tire_brands,id',
            'tire_model_id' => 'required|exists:tire_models,id',
            'tire_size_id' => 'required|exists:tire_sizes,id',
            'condition' => 'required|string',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $model = TireModel::with('sizes')->find($this->input('tire_model_id'));
            if (! $model) {
                return;
            }
            if ((int) $model->tire_brand_id !== (int) $this->input('tire_brand_id')) {
                $validator->errors()->add('tire_model_id', $model->code.' no pertenece a esa marca.');
            }
            if (! $model->sizes->contains('id', (int) $this->input('tire_size_id'))) {
                $validator->errors()->add('tire_size_id', $model->code.' no se fabrica en esa medida.');
            }
        });
    }
}
