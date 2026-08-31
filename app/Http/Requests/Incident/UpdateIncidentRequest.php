<?php

namespace App\Http\Requests\Incident;

use App\Models\SafetyIncident;
use App\Http\Requests\Concerns\ValidatesGeolocation;
use App\Http\Requests\Concerns\ValidatesInvolvedEmployees;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentRequest extends FormRequest
{
    use ValidatesInvolvedEmployees;
    use ValidatesGeolocation;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date'                  => ['sometimes', 'date'],
            'time'                  => ['nullable', 'string'],
            'location'              => ['sometimes', 'string', 'max:255'],
            'type'                  => ['sometimes', Rule::in(SafetyIncident::TYPES)],
            'severity'              => ['sometimes', 'in:low,medium,high,critical'],
            'description'           => ['sometimes', 'string'],
            'immediate_cause'       => ['nullable', 'string'],
            'root_cause'            => ['nullable', 'string'],
            'corrective_action'     => ['nullable', 'string'],
            'corrective_action_due' => ['nullable', 'date'],
            'status'                => ['sometimes', 'in:open,in_progress,closed'],
        ] + $this->geolocationRules() + $this->involvedEmployeesRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->geolocationMessages();
    }
}
