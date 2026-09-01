<?php

namespace App\Http\Requests\Breach;

use App\Http\Requests\Concerns\ValidatesInvolvedEmployees;
use App\Http\Requests\Concerns\ConvertsLegacyInvolved;
use App\Http\Requests\Concerns\ValidatesInvolvedPeople;
use Illuminate\Foundation\Http\FormRequest;

class StoreBreachRequest extends FormRequest
{
    use ValidatesInvolvedEmployees;
    use ConvertsLegacyInvolved;
    use ValidatesInvolvedPeople;
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', $this->tenantEmployeeExists()],
            'location'    => ['nullable', 'string', 'max:255'],
            'date'        => ['required', 'date'],
            'type'        => ['required', 'string', 'max:100'],
            'severity'    => ['required', 'in:low,medium,high,critical'],
            'description' => ['required', 'string'],
            'sanction'    => ['nullable', 'string'],
        ] + $this->involvedPeopleRules();
    }
}
