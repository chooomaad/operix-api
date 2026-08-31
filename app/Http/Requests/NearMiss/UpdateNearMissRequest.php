<?php

namespace App\Http\Requests\NearMiss;

use App\Http\Requests\Concerns\ValidatesGeolocation;
use App\Http\Requests\Concerns\ValidatesInvolvedEmployees;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNearMissRequest extends FormRequest
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
            'severity'              => ['sometimes', 'in:low,medium,high'],
            'description'           => ['sometimes', 'string'],
            'potential_consequence' => ['nullable', 'string'],
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
