<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contractor\StoreContractorRequest;
use App\Http\Requests\Contractor\UpdateContractorRequest;
use App\Http\Resources\ContractorResource;
use App\Models\Contractor;
use App\Traits\HandlesApiResources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractorController extends Controller
{
    use HandlesApiResources;

    public function index(Request $request): JsonResponse
    {
        $query = Contractor::query()->withCount('employees');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('company_name', 'ilike', "%{$s}%")
                ->orWhere('activite',   'ilike', "%{$s}%")
                ->orWhere('contact_nom','ilike', "%{$s}%")
            );
        }

        if ($request->filled('status')) $query->where('status', $request->status);

        if ($request->boolean('expiring_soon')) {
            $query->where('status', 'active')
                  ->whereNotNull('contract_end')
                  ->whereDate('contract_end', '<=', now()->addDays(30))
                  ->whereDate('contract_end', '>=', now());
        }

        $result = $this->paginateQuery($query->orderBy('company_name'), $request);
        $result['data'] = ContractorResource::collection($result['data']);

        return response()->json($result);
    }

    public function store(StoreContractorRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('operix/contractors', 'public');
        }

        $contractor = Contractor::create($data);

        return response()->json(new ContractorResource($contractor), 201);
    }

    public function show(int $id): JsonResponse
    {
        $contractor = Contractor::withCount('employees')->with('employees')->findOrFail($id);
        return response()->json(new ContractorResource($contractor));
    }

    public function update(UpdateContractorRequest $request, int $id): JsonResponse
    {
        $contractor = Contractor::findOrFail($id);
        $contractor->update($request->validated());

        return response()->json(new ContractorResource($contractor));
    }

    public function destroy(int $id): JsonResponse
    {
        Contractor::findOrFail($id)->delete();
        return response()->json(['message' => 'Sous-traitant supprimé.']);
    }
}
