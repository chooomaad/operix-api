<?php

namespace App\Http\Requests\Incident;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date'                  => ['required', 'date'],
            'time'                  => ['nullable', 'string'],
            'location'              => ['required', 'string', 'max:255'],
            'type'                  => ['required', 'in:LTI,FIRE,MTC,RWC,FIRST_AID,HPI'],
            'severity'              => ['required', 'in:low,medium,high,critical'],
            'description'           => ['required', 'string'],
            'immediate_cause'       => ['nullable', 'string'],
            'root_cause'            => ['nullable', 'string'],
            'corrective_action'     => ['nullable', 'string'],
            'corrective_action_due' => ['nullable', 'date'],
            'status'                => ['nullable', 'in:open,in_progress,closed'],
            'employees'             => ['nullable', 'array'],
            'employees.*'           => ['integer'],
            'image'                 => ['nullable', 'image', 'max:5120'],
        ];
    }
}
