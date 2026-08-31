<?php

namespace App\Http\Requests\Breach;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBreachRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date'        => ['sometimes', 'date'],
            'type'        => ['sometimes', 'string', 'max:100'],
            'location'    => ['nullable', 'string', 'max:255'],
            'severity'    => ['sometimes', 'in:low,medium,high,critical'],
            'description' => ['sometimes', 'string'],
            'corrective_action' => ['nullable', 'string'],
            'employees'   => ['nullable', 'array'],
            'employees.*' => ['integer', 'exists:employees,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }
}
