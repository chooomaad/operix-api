<?php

namespace App\Http\Requests\GembaWalk;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGembaWalkRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date'            => ['sometimes', 'date'],
            'zone'            => ['sometimes', 'string', 'max:255'],
            'objective'       => ['nullable', 'string'],
            'auditor'         => ['sometimes', 'string', 'max:255'],
            'observation'     => ['sometimes', 'string'],
            'action_required' => ['nullable', 'string'],
            'responsible'     => ['nullable', 'string', 'max:255'],
            'due_date'        => ['nullable', 'date'],
            'priority'        => ['sometimes', 'in:low,medium,high'],
            'status'          => ['sometimes', 'in:open,in_progress,resolved'],
            'image'           => ['nullable', 'image', 'max:5120'],
        ];
    }
}
