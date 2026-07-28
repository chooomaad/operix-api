<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanController extends Controller
{
    /**
     * Liste publique des plans (consommée par le site marketing pour le pricing).
     * N'expose que les plans actifs et publics.
     */
    public function index(): AnonymousResourceCollection
    {
        $plans = Plan::query()
            ->where('active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->get();

        return PlanResource::collection($plans);
    }
}
