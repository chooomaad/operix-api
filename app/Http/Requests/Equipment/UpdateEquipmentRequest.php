<?php

namespace App\Http\Requests\Equipment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $equipment = $this->route('id');

        return [
            'code'                      => ['nullable', 'string', 'max:255', Rule::unique('equipment', 'code')->ignore($equipment)],
            'name'                      => ['sometimes', 'string', 'max:255'],
            'category'                  => ['sometimes', 'in:vehicle,crane,forklift,electrical,pressure,fire,ppe,tool,other'],
            'brand'                     => ['nullable', 'string', 'max:255'],
            'model'                     => ['nullable', 'string', 'max:255'],
            'serial_number'             => ['nullable', 'string', 'max:255'],
            'purchase_date'             => ['nullable', 'date'],
            'last_inspection'           => ['nullable', 'date'],
            'next_inspection'           => ['nullable', 'date'],
            'inspection_frequency_days' => ['nullable', 'integer', 'min:1'],
            'status'                    => ['sometimes', 'in:operational,maintenance,out_of_service,retired'],
            'location'                  => ['nullable', 'string', 'max:255'],
            'assigned_to'               => ['nullable', 'integer', 'exists:employees,id'],
            'notes'                     => ['nullable', 'string'],
        ];
    }
}
