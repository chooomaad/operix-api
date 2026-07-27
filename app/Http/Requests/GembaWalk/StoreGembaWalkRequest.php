<?php

namespace App\Http\Requests\GembaWalk;

use Illuminate\Foundation\Http\FormRequest;

class StoreGembaWalkRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date'            => ['required', 'date'],
            'zone'            => ['required', 'string', 'max:255'],
            'objective'       => ['nullable', 'string'],
            'auditor'         => ['required', 'string', 'max:255'],
            'observation'     => ['required', 'string'],
            'action_required' => ['nullable', 'string'],
            'responsible'     => ['nullable', 'string', 'max:255'],
            'due_date'        => ['nullable', 'date'],
            'priority'        => ['nullable', 'in:low,medium,high'],
            'status'          => ['nullable', 'in:open,in_progress,resolved'],
            'image'           => ['nullable', 'image', 'max:5120'],
        ];
    }
}
