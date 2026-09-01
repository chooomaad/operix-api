<?php

namespace App\Http\Requests\Breach;

use App\Http\Requests\Concerns\ValidatesInvolvedEmployees;
use App\Http\Requests\Concerns\ConvertsLegacyInvolved;
use App\Http\Requests\Concerns\ValidatesInvolvedPeople;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBreachRequest extends FormRequest
{
    use ValidatesInvolvedEmployees;
    use ConvertsLegacyInvolved;
    use ValidatesInvolvedPeople;
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date'        => ['sometimes', 'date'],
            'type'        => ['sometimes', 'string', 'max:100'],
            'location'    => ['nullable', 'string', 'max:255'],
            'severity'    => ['sometimes', 'in:low,medium,high,critical'],
            'status'      => ['sometimes', 'in:open,in_progress,closed'],
            'description' => ['sometimes', 'string'],
            'corrective_action' => ['nullable', 'string'],
            'employee_id' => ['nullable', 'integer', $this->tenantEmployeeExists()],
        ] + $this->involvedPeopleRules();
    }
}
