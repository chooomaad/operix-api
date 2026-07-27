<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'matricule'           => ['required', 'string', 'max:50', 'unique:employees,matricule'],
            'nom'                 => ['required', 'string', 'max:100'],
            'prenom'              => ['required', 'string', 'max:100'],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:20'],
            'poste'               => ['required', 'string', 'max:150'],
            'type_contrat'        => ['required', 'in:CDI,CDD,Stage,Prestataire,Autre'],
            'department_id'       => ['nullable', 'integer', 'exists:departments,id'],
            'date_embauche'       => ['nullable', 'date'],
            'date_fin_contrat'    => ['nullable', 'date', 'after:date_embauche'],
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
            'photo'               => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'matricule.required'    => 'Le matricule est obligatoire.',
            'matricule.unique'      => 'Ce matricule existe déjà.',
            'nom.required'          => 'Le nom est obligatoire.',
            'prenom.required'       => 'Le prénom est obligatoire.',
            'poste.required'        => 'Le poste est obligatoire.',
            'type_contrat.required' => 'Le type de contrat est obligatoire.',
            'type_contrat.in'       => 'Type de contrat invalide.',
            'photo.image'           => 'Le fichier doit être une image.',
            'photo.max'             => 'La photo ne doit pas dépasser 2 Mo.',
        ];
    }
}
