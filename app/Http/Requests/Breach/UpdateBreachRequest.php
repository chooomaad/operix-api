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
            'severity'    => ['sometimes', 'in:avertissement,blame,mise_a_pied,licenciement'],
            'description' => ['sometimes', 'string'],
            'sanction'    => ['nullable', 'string'],
        ];
    }
}
