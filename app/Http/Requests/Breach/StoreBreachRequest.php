<?php

namespace App\Http\Requests\Breach;

use Illuminate\Foundation\Http\FormRequest;

class StoreBreachRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date'        => ['required', 'date'],
            'type'        => ['required', 'string', 'max:100'],
            'severity'    => ['required', 'in:avertissement,blame,mise_a_pied,licenciement'],
            'description' => ['required', 'string'],
            'sanction'    => ['nullable', 'string'],
        ];
    }
}
