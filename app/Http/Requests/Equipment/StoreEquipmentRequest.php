<?php

namespace App\Http\Requests\Equipment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code'                      => ['nullable', 'string', 'max:255', Rule::unique('equipment', 'code')->where('tenant_id', $this->user()->tenant_id)],
            'name'                      => ['required', 'string', 'max:255'],
            'category'                  => ['required', 'in:vehicle,crane,forklift,electrical,pressure,fire,ppe,tool,other'],
            'brand'                     => ['nullable', 'string', 'max:255'],
            'model'                     => ['nullable', 'string', 'max:255'],
            'serial_number'             => ['nullable', 'string', 'max:255'],
            'purchase_date'             => ['nullable', 'date'],
            'last_inspection'           => ['nullable', 'date'],
            'next_inspection'           => ['nullable', 'date'],
            'inspection_frequency_days' => ['nullable', 'integer', 'min:1'],
            'status'                    => ['nullable', 'in:operational,maintenance,out_of_service,retired'],
            'location'                  => ['nullable', 'string', 'max:255'],
            'assigned_to'               => ['nullable', 'integer', 'exists:employees,id'],
            'photo'                     => ['nullable', 'image', 'max:5120'],
            'notes'                     => ['nullable', 'string'],
        ];
    }
}
