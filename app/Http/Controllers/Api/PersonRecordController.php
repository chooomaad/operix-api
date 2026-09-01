<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\Formation;
use App\Models\MedicalVisit;
use App\Services\TenantFileService;
use App\Support\People;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dossiers RH (formations / certifications / visites médicales) rattachés à
 * N'IMPORTE QUELLE personne (employee/contractor/visitor/intern), via
 * (person_type, person_id). Même écosystème que les employés, généralisé.
 */
class PersonRecordController extends Controller
{
    /** Config par type de dossier : modèle, colonne image, règles, défauts. */
    private function config(string $record): array
    {
        return match ($record) {
            'formations' => [
                'model' => Formation::class, 'image' => 'certificat',
                'rules' => [
                    'titre'        => ['required', 'string', 'max:255'],
                    'organisme'    => ['nullable', 'string', 'max:255'],
                    'date_debut'   => ['required', 'date'],
                    'date_fin'     => ['nullable', 'date', 'after_or_equal:date_debut'],
                    'type'         => ['nullable', 'in:interne,externe,elearning,habilitation,autre'],
                    'statut'       => ['nullable', 'in:planifiee,en_cours,terminee,annulee'],
                    'observations' => ['nullable', 'string'],
                ],
                'defaults' => ['type' => 'externe', 'statut' => 'terminee'],
            ],
            'certifications' => [
                'model' => Certification::class, 'image' => 'document',
                'rules' => [
                    'titre'           => ['required', 'string', 'max:255'],
                    'numero'          => ['nullable', 'string', 'max:100'],
                    'organisme'       => ['nullable', 'string', 'max:255'],
                    'date_obtention'  => ['required', 'date'],
                    'date_expiration' => ['nullable', 'date', 'after:date_obtention'],
                ],
                'defaults' => [],
            ],
            'medical-visits' => [
                'model' => MedicalVisit::class, 'image' => 'document',
                'rules' => [
                    'date'             => ['required', 'date'],
                    'type'             => ['nullable', 'in:embauche,periodique,reprise,spontanee'],
                    'resultat'         => ['nullable', 'in:apte,apte_restrictions,inapte'],
                    'restrictions'     => ['nullable', 'string'],
                    'prochaine_visite' => ['nullable', 'date', 'after:date'],
                    'medecin'          => ['nullable', 'string', 'max:255'],
                    'observations'     => ['nullable', 'string'],
                ],
                'defaults' => ['type' => 'periodique', 'resultat' => 'apte'],
            ],
            default => abort(404),
        };
    }

    private function guardPerson(string $type, int $id): void
    {
        abort_unless(in_array($type, People::TYPES, true) && People::exists($type, $id), 404);
    }

    public function index(string $type, int $id, string $record): JsonResponse
    {
        $this->guardPerson($type, $id);
        $model = $this->config($record)['model'];
        $rows = $model::where('person_type', $type)->where('person_id', $id)->latest()->get();
        return response()->json($rows);
    }

    public function store(Request $request, string $type, int $id, string $record): JsonResponse
    {
        $this->guardPerson($type, $id);
        $cfg = $this->config($record);
        $data = $request->validate($cfg['rules'] + ['image' => ['nullable', 'image', 'max:5120']]);

        $payload = collect($data)->except('image')->all() + $cfg['defaults'];
        $payload['person_type'] = $type;
        $payload['person_id']   = $id;
        if ($request->hasFile('image')) {
            $payload[$cfg['image']] = app(TenantFileService::class)->store($request->file('image'), $record);
        }

        return response()->json($cfg['model']::create($payload), 201);
    }

    public function update(Request $request, string $type, int $id, string $record, int $recordId): JsonResponse
    {
        $this->guardPerson($type, $id);
        $cfg = $this->config($record);
        $row = $cfg['model']::where('person_type', $type)->where('person_id', $id)->findOrFail($recordId);

        $rules = collect($cfg['rules'])->map(fn ($r) => array_map(fn ($x) => $x === 'required' ? 'sometimes' : $x, $r))->all();
        $data = $request->validate($rules + ['image' => ['nullable', 'image', 'max:5120']]);

        $payload = collect($data)->except('image')->all();
        if ($request->hasFile('image')) {
            $payload[$cfg['image']] = app(TenantFileService::class)->replace($row->{$cfg['image']}, $request->file('image'), $record);
        }
        $row->update($payload);
        return response()->json($row);
    }

    public function destroy(string $type, int $id, string $record, int $recordId): JsonResponse
    {
        $this->guardPerson($type, $id);
        $model = $this->config($record)['model'];
        $model::where('person_type', $type)->where('person_id', $id)->findOrFail($recordId)->delete();
        return response()->json(['message' => 'Supprimé.']);
    }
}
