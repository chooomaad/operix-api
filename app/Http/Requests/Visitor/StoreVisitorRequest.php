<?php

namespace App\Http\Requests\Visitor;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nom'              => ['required', 'string', 'max:255'],
            'prenom'           => ['required', 'string', 'max:255'],
            'entreprise'       => ['nullable', 'string', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:20'],
            'email'            => ['nullable', 'email'],
            'cin'              => ['nullable', 'string', 'max:50'],
            'badge_number'     => ['nullable', 'string', 'max:50'],
            'motif'            => ['required', 'string', 'max:255'],
            'personne_visitee' => ['nullable', 'string', 'max:255'],
            'department'       => ['nullable', 'string', 'max:255'],
            'vehicle_plate'    => ['nullable', 'string', 'max:50'],
            'photo'            => ['nullable', 'image', 'max:5120'],
            'notes'            => ['nullable', 'string'],
        ];
    }
}
