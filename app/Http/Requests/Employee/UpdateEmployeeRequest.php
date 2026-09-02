<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $employeeId = $this->route('id');

        return [
            'matricule'           => ['sometimes', 'string', 'max:50', Rule::unique('employees', 'matricule')->where('tenant_id', $this->user()->tenant_id)->ignore($employeeId)],
            'nom'                 => ['sometimes', 'string', 'max:100'],
            'prenom'              => ['sometimes', 'string', 'max:100'],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:20'],
            'poste'               => ['sometimes', 'string', 'max:150'],
            'type_contrat'        => ['sometimes', 'in:CDI,CDD,Stage,Prestataire,Autre'],
            'department_id'       => ['nullable', 'integer', 'exists:departments,id'],
            'date_embauche'       => ['nullable', 'date'],
            'date_fin_contrat'    => ['nullable', 'date'],
            'gender'              => ['nullable', 'in:M,F'],
            'date_naissance'      => ['nullable', 'date', 'before:today'],
            'nationalite'         => ['nullable', 'string', 'max:100'],
            'lieu_naissance'      => ['nullable', 'string', 'max:150'],
            'adresse'             => ['nullable', 'string', 'max:255'],
            'num_cni'             => ['nullable', 'string', 'max:50'],
            'nni'                 => ['nullable', 'string', 'max:20'],
            'contact_urgence_nom' => ['nullable', 'string', 'max:150'],
            'contact_urgence_tel' => ['nullable', 'string', 'max:20'],
            'is_active'           => ['boolean'],
            'photo'               => ['nullable', 'image', 'max:12288'],
        ];
    }
}
