<?php

namespace App\Http\Requests\Breach;

use Illuminate\Foundation\Http\FormRequest;

class StoreBreachRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'employees'   => ['nullable', 'array'],
            'employees.*' => ['integer', 'exists:employees,id'],
            'location'    => ['nullable', 'string', 'max:255'],
            'date'        => ['required', 'date'],
            'type'        => ['required', 'string', 'max:100'],
            'severity'    => ['required', 'in:low,medium,high,critical'],
            'description' => ['required', 'string'],
            'sanction'    => ['nullable', 'string'],
        ];
    }
}
