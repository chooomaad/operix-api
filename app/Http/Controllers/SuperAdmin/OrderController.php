<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()->with('plan:id,slug,name')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q
                ->where('reference', 'ilike', "%{$s}%")
                ->orWhere('company_name', 'ilike', "%{$s}%")
                ->orWhere('email', 'ilike', "%{$s}%"));
        }

        return response()->json($query->paginate(min((int) $request->integer('per_page', 25), 100)));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(
            Order::with(['plan', 'payments', 'tenant:id,name,slug,status'])->findOrFail($id)
        );
    }
}
