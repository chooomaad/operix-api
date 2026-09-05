<?php

namespace App\Http\Requests\PropertyDamage;

use App\Http\Requests\Concerns\ConvertsLegacyInvolved;
use App\Http\Requests\Concerns\ValidatesGeolocation;
use App\Http\Requests\Concerns\ValidatesInvolvedPeople;
use Illuminate\Foundation\Http\FormRequest;

class StorePropertyDamageRequest extends FormRequest
{
    use ConvertsLegacyInvolved;
    use ValidatesInvolvedPeople;
    use ValidatesGeolocation;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date'                  => ['required', 'date'],
            'time'                  => ['nullable', 'string'],
            'location'              => ['required', 'string', 'max:255'],
            'type'                  => ['required', 'in:vehicle,equipment,infrastructure,cargo,container,other'],
            'severity'              => ['required', 'in:low,medium,high,critical'],
            'description'           => ['required', 'string'],
            'estimated_cost'        => ['nullable', 'numeric', 'min:0'],
            'immediate_cause'       => ['nullable', 'string'],
            'corrective_action'     => ['nullable', 'string'],
            'corrective_action_due' => ['nullable', 'date'],
            'status'                => ['nullable', 'in:open,in_progress,closed'],
            'image'                 => ['nullable', 'image', 'max:5120'],
            'report_file'           => ['nullable', 'file', 'mimes:pdf', 'max:15360'],
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
