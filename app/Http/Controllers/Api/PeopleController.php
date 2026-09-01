<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Breach;
use App\Models\EnvironmentReport;
use App\Models\SafetyIncident;
use App\Models\SafetyNearMiss;
use App\Support\People;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Recherche unifiée « personnes » (employee/contractor/visitor/intern) et
 * historique HSSE de n'importe quelle personne — identité TOUJOURS (type + id).
 */
class PeopleController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q'    => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:employee,contractor,visitor,intern'],
        ]);

        $results = People::search(
            (string) $request->query('q', ''),
            $request->query('type'),
            (int) $request->integer('limit', 10),
        );

        return response()->json(['data' => $results]);
    }

    /**
     * Historique HSSE d'une personne (tous modules), par containment jsonb
     * `involved_people @> [{type,id}]`. Vaut pour les 4 types de personnes.
     */
    public function history(Request $request, string $type, int $id): JsonResponse
    {
        abort_unless(in_array($type, People::TYPES, true), 404);

        $needle = json_encode([['type' => $type, 'id' => $id]]);
        $contain = fn ($model) => $model::whereRaw('involved_people @> ?::jsonb', [$needle])
            ->orderByDesc('date')->get();

        $incidents = $contain(SafetyIncident::class)->map(fn ($i) => [
            'id' => $i->id, 'reference' => $i->reference, 'date' => $i->date?->format('d/m/Y'),
            'location' => $i->location, 'type' => $i->type, 'severity' => $i->severity,
            'status' => $i->status, 'description' => Str::limit($i->description, 80),
        ]);
        $nearMiss = $contain(SafetyNearMiss::class)->map(fn ($n) => [
            'id' => $n->id, 'reference' => $n->reference, 'date' => $n->date?->format('d/m/Y'),
            'location' => $n->location, 'severity' => $n->severity, 'status' => $n->status,
            'description' => Str::limit($n->description, 80),
        ]);
        $breaches = $contain(Breach::class)->map(fn ($b) => [
            'id' => $b->id, 'reference' => $b->reference, 'date' => $b->date?->format('d/m/Y'),
            'type' => $b->type, 'severity' => $b->severity, 'status' => $b->status,
            'description' => Str::limit($b->description, 80),
        ]);
        $environment = $contain(EnvironmentReport::class)->map(fn ($e) => [
            'id' => $e->id, 'reference' => $e->reference, 'date' => $e->date?->format('d/m/Y'),
            'location' => $e->location, 'type' => $e->type, 'severity' => $e->severity,
            'status' => $e->status, 'description' => Str::limit($e->description, 80),
        ]);

        $person = People::resolve([['type' => $type, 'id' => $id]])->first();

        return response()->json([
            'person'      => $person,
            'incidents'   => $incidents,
            'near_miss'   => $nearMiss,
            'breaches'    => $breaches,
            'environment' => $environment,
            'stats' => [
                'incidents_count'   => $incidents->count(),
                'near_miss_count'   => $nearMiss->count(),
                'breaches_count'    => $breaches->count(),
                'environment_count' => $environment->count(),
            ],
        ]);
    }
}
