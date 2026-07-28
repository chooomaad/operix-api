<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestion des plans commerciaux (prix administrables) — réservé au super_admin.
 */
class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Plan::orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $plan = Plan::create($data);

        return response()->json($plan, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);
        $plan->update($this->validateData($request, $plan->id));

        return response()->json($plan);
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'slug'             => [$ignoreId ? 'sometimes' : 'required', 'string', 'max:50', Rule::unique('plans', 'slug')->ignore($ignoreId)],
            'name'             => [$ignoreId ? 'sometimes' : 'required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'price_monthly'    => ['nullable', 'integer', 'min:0'],   // centimes EUR
            'price_yearly'     => ['nullable', 'integer', 'min:0'],
            'currency'         => ['sometimes', 'string', 'size:3'],
            'max_employees'    => ['nullable', 'integer', 'min:1'],
            'storage_limit_mb' => ['nullable', 'integer', 'min:1'],
            'features'         => ['nullable', 'array'],
            'contact_sales'    => ['boolean'],
            'is_public'        => ['boolean'],
            'sort_order'       => ['sometimes', 'integer'],
            'active'           => ['boolean'],
        ]);
    }
}
