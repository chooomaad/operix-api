<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Intern;
use App\Traits\HandlesApiResources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternController extends Controller
{
    use HandlesApiResources;

    public function index(Request $request): JsonResponse
    {
        $query = Intern::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q
                ->where('nom', 'ilike', "%{$s}%")
                ->orWhere('prenom', 'ilike', "%{$s}%")
                ->orWhere('reference', 'ilike', "%{$s}%")
                ->orWhere('etablissement', 'ilike', "%{$s}%"));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $result = $this->paginateQuery($query->orderByDesc('created_at'), $request);
        return response()->json($result);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $intern = $this->createWithReference('INT', Intern::class, $data);
        return response()->json($intern, 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Intern::findOrFail($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $intern = Intern::findOrFail($id);
        $intern->update($this->validateData($request, false));
        return response()->json($intern);
    }

    public function destroy(int $id): JsonResponse
    {
        Intern::findOrFail($id)->delete();
        return response()->json(['message' => 'Stagiaire supprimé.']);
    }

    private function validateData(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'nom'           => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'prenom'        => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'etablissement' => ['nullable', 'string', 'max:255'],
            'encadrant'     => ['nullable', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'email'         => ['nullable', 'email', 'max:255'],
            'date_debut'    => ['nullable', 'date'],
            'date_fin'      => ['nullable', 'date'],
            'status'        => ['nullable', 'in:active,ended'],
            'is_active'     => ['nullable', 'boolean'],
            'notes'         => ['nullable', 'string'],
        ]);
    }
}
