<?php

namespace App\Http\Requests\Environment;

use App\Http\Requests\Concerns\ValidatesGeolocation;
use App\Http\Requests\Concerns\ConvertsLegacyInvolved;
use App\Http\Requests\Concerns\ValidatesInvolvedPeople;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEnvironmentRequest extends FormRequest
{
    use ConvertsLegacyInvolved;
    use ValidatesInvolvedPeople;
    use ValidatesGeolocation;

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
        ] + $this->geolocationRules() + $this->involvedPeopleRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->geolocationMessages();
    }
}
