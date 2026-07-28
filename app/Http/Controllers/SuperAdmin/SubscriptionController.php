<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::query()
            ->with(['tenant:id,name,slug,status', 'plan:id,slug,name'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(min((int) $request->integer('per_page', 25), 100)));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(
            Subscription::with(['tenant', 'plan', 'order:id,reference,amount,currency,status'])->findOrFail($id)
        );
    }
}
