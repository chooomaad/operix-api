<?php

namespace App\Http\Requests\Risk;

use App\Models\RiskAction;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRiskActionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'description'    => [$required, 'string'],
            'type'           => ['nullable', Rule::in(['corrective', 'preventive'])],
            'responsible_id' => ['nullable', 'integer', function ($a, $v, $fail) {
                if ($v !== null && ! User::whereKey($v)->exists()) {
                    $fail('Le responsable sélectionné est introuvable dans votre organisation.');
                }
            }],
            'due_date'  => ['nullable', 'date'],
            'priority'  => ['nullable', Rule::in(RiskAction::PRIORITIES)],
            'status'    => ['nullable', Rule::in(RiskAction::STATUSES)],
            'budget'    => ['nullable', 'numeric', 'min:0'],
            'proof'     => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'closed_at' => ['nullable', 'date'],
        ];
    }
}
