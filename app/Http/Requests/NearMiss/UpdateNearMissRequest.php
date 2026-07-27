<?php

namespace App\Http\Requests\NearMiss;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNearMissRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date'                  => ['sometimes', 'date'],
            'time'                  => ['nullable', 'string'],
            'location'              => ['sometimes', 'string', 'max:255'],
            'severity'              => ['sometimes', 'in:low,medium,high'],
            'description'           => ['sometimes', 'string'],
            'potential_consequence' => ['nullable', 'string'],
            'corrective_action'     => ['nullable', 'string'],
            'corrective_action_due' => ['nullable', 'date'],
            'status'                => ['sometimes', 'in:open,in_progress,closed'],
            'employees'             => ['nullable', 'array'],
            'employees.*'           => ['integer'],
        ];
    }
}
