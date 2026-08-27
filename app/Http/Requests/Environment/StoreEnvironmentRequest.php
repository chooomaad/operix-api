<?php

namespace App\Http\Requests\Environment;

use App\Http\Requests\Concerns\ValidatesGeolocation;
use Illuminate\Foundation\Http\FormRequest;

class StoreEnvironmentRequest extends FormRequest
{
    use ValidatesGeolocation;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date'                  => ['required', 'date'],
            'location'              => ['required', 'string', 'max:255'],
            'type'                  => ['required', 'in:spill,emission,waste,noise,other'],
            'severity'              => ['required', 'in:low,medium,high,critical'],
            'description'           => ['required', 'string'],
            'impact'                => ['nullable', 'string'],
            'corrective_action'     => ['nullable', 'string'],
            'corrective_action_due' => ['nullable', 'date'],
            'status'                => ['nullable', 'in:open,in_progress,closed'],
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
