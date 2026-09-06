<?php

namespace App\Http\Requests\Risk;

use App\Models\Department;
use App\Models\Risk;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRiskRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date_identification' => ['sometimes', 'date'],
            'location'            => ['sometimes', 'string', 'max:255'],
            'department_id'       => ['nullable', 'integer', $this->existsInTenant(Department::class)],
            'activity'            => ['nullable', 'string', 'max:255'],
            'category'            => ['sometimes', Rule::in(Risk::CATEGORIES)],
            'assessment_type'     => ['sometimes', Rule::in(Risk::ASSESSMENT_TYPES)],
            'danger'              => ['sometimes', 'string'],
            'risk_description'    => ['sometimes', 'string'],
            'causes'              => ['nullable', 'string'],
            'consequences'        => ['nullable', 'string'],
            'exposed_persons'     => ['nullable', 'string'],
            'existing_controls'   => ['nullable', 'string'],
            'owner_id'            => ['nullable', 'integer', $this->existsInTenant(User::class)],

            'probability'         => ['sometimes', 'integer', 'between:1,5'],
            'severity'            => ['sometimes', 'integer', 'between:1,5'],

            'controls'               => ['nullable', 'array'],
            'controls.*.hierarchy'   => ['required_with:controls', Rule::in(Risk::CONTROL_HIERARCHY)],
            'controls.*.description' => ['required_with:controls', 'string', 'max:1000'],

            'residual_probability' => ['nullable', 'integer', 'between:1,5'],
            'residual_severity'    => ['nullable', 'integer', 'between:1,5'],

            'status'      => ['sometimes', Rule::in(Risk::STATUSES)],
            'review_date' => ['nullable', 'date'],
        ];
    }

    protected function existsInTenant(string $model): \Closure
    {
        return function ($attr, $value, $fail) use ($model) {
            if ($value !== null && ! $model::whereKey($value)->exists()) {
                $fail('La référence sélectionnée est introuvable dans votre organisation.');
            }
        };
    }
}
