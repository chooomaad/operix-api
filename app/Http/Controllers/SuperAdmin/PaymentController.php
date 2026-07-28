<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()->with('order:id,reference,company_name')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('provider')) {
            $query->where('provider', $request->provider);
        }

        return response()->json($query->paginate(min((int) $request->integer('per_page', 25), 100)));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Payment::with('order')->findOrFail($id));
    }
}
