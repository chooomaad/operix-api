<?php

namespace App\Http\Requests\Contractor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'company_name'     => ['sometimes', 'string', 'max:255'],
            'contact_nom'      => ['nullable', 'string', 'max:255'],
            'contact_phone'    => ['nullable', 'string', 'max:20'],
            'contact_email'    => ['nullable', 'email'],
            'activite'         => ['sometimes', 'string', 'max:255'],
            'num_registre'     => ['nullable', 'string', 'max:255'],
            'contract_start'   => ['nullable', 'date'],
            'contract_end'     => ['nullable', 'date'],
            'status'           => ['sometimes', 'in:active,suspended,expired'],
            'zones_autorisees' => ['nullable', 'string'],
            'notes'            => ['nullable', 'string'],
        ];
    }
}
