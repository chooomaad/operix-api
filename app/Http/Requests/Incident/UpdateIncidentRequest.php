<?php

namespace App\Http\Requests\Incident;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIncidentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date'                  => ['sometimes', 'date'],
            'time'                  => ['nullable', 'string'],
            'location'              => ['sometimes', 'string', 'max:255'],
            'type'                  => ['sometimes', 'in:LTI,FIRE,MTC,RWC,FIRST_AID,HPI'],
            'severity'              => ['sometimes', 'in:low,medium,high,critical'],
            'description'           => ['sometimes', 'string'],
            'immediate_cause'       => ['nullable', 'string'],
            'root_cause'            => ['nullable', 'string'],
            'corrective_action'     => ['nullable', 'string'],
            'corrective_action_due' => ['nullable', 'date'],
            'status'                => ['sometimes', 'in:open,in_progress,closed'],
            'employees'             => ['nullable', 'array'],
            'employees.*'           => ['integer'],
        ];
    }
}
