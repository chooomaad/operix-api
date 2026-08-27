<?php

namespace App\Http\Requests\NearMiss;

use App\Http\Requests\Concerns\ValidatesGeolocation;
use Illuminate\Foundation\Http\FormRequest;

class StoreNearMissRequest extends FormRequest
{
    use ValidatesGeolocation;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date'                  => ['required', 'date'],
            'time'                  => ['nullable', 'string'],
            'location'              => ['required', 'string', 'max:255'],
            'severity'              => ['required', 'in:low,medium,high'],
            'description'           => ['required', 'string'],
            'potential_consequence' => ['nullable', 'string'],
            'corrective_action'     => ['nullable', 'string'],
            'corrective_action_due' => ['nullable', 'date'],
            'status'                => ['nullable', 'in:open,in_progress,closed'],
            'employees'             => ['nullable', 'array'],
            'employees.*'           => ['integer'],
            'image'                 => ['nullable', 'image', 'max:5120'],
        ] + $this->geolocationRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->geolocationMessages();
    }
}
