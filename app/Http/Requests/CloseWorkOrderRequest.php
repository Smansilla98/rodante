<?php

namespace App\Http\Requests;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CloseWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->route('workOrder');
        if (! Gate::forUser($this->user())->check('view', $workOrder)) {
            abort(404);
        }

        return Gate::forUser($this->user())->allows('manage', $workOrder);
    }

    public function rules(): array
    {
        return [
            'cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
