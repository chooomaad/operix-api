<?php

namespace App\Http\Requests\Environment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEnvironmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date'                  => ['sometimes', 'date'],
            'location'              => ['sometimes', 'string', 'max:255'],
            'type'                  => ['sometimes', 'in:spill,emission,waste,noise,other'],
            'severity'              => ['sometimes', 'in:low,medium,high,critical'],
            'description'           => ['sometimes', 'string'],
            'impact'                => ['nullable', 'string'],
            'corrective_action'     => ['nullable', 'string'],
            'corrective_action_due' => ['nullable', 'date'],
            'status'                => ['sometimes', 'in:open,in_progress,closed'],
        ];
    }
}
