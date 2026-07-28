<?php

namespace App\Services;

use App\Models\DemoRequest;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Provisioning d'entreprise (Tenant + premier company_admin + Subscription + activation).
 *
 * TRANSACTIONNEL et IDEMPOTENT : un webhook reçu N fois ne crée jamais plusieurs tenants.
 * L'idempotence est ancrée sur la ressource source (order / demo_request) : si elle porte
 * déjà un tenant_id, on renvoie l'existant sans rien recréer. Toute la création se fait dans
 * une transaction avec verrou → aucun tenant partiellement créé ne subsiste en cas d'échec.
 *
 * Le même service sert le parcours PAYANT (order) et le parcours DÉMO/TRIAL (demo_request) :
 * la logique n'est pas dupliquée.
 */
class ProvisioningService
{
    public function __construct(private ActivationService $activations)
    {
    }

    /** Provisioning après paiement confirmé (statut tenant = active). */
    public function provisionFromOrder(Order $order): ProvisioningResult
    {
        return DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->tenant_id) {
                return $this->existingResult($order->tenant);
            }

            $plan = $order->plan;

            $result = $this->createEnvironment(
                companyName: $order->company_name,
                adminName: $order->contact_name,
                adminEmail: $order->email,
                plan: $plan,
                tenantStatus: 'active',
                subscriptionStatus: 'active',
                billingCycle: $order->billing_cycle,
                order: $order,
                trialDays: null,
            );

            $order->update(['tenant_id' => $result->tenant->id]);

            return $result;
        });
    }

    /** Provisioning d'un environnement de démonstration (trial) depuis une demande de démo. */
    public function provisionTrialFromDemo(DemoRequest $demo, Plan $plan, int $trialDays = 14): ProvisioningResult
    {
        return DB::transaction(function () use ($demo, $plan, $trialDays) {
            $demo = DemoRequest::whereKey($demo->id)->lockForUpdate()->firstOrFail();

            if ($demo->tenant_id) {
                return $this->existingResult($demo->tenant);
            }

            $result = $this->createEnvironment(
                companyName: $demo->company_name,
                adminName: $demo->contact_name,
                adminEmail: $demo->email,
                plan: $plan,
                tenantStatus: 'trial',
                subscriptionStatus: 'trialing',
                billingCycle: null,
                order: null,
                trialDays: $trialDays,
            );

            $demo->update(['tenant_id' => $result->tenant->id, 'status' => 'converted']);

            return $result;
        });
    }

    // ── Interne ────────────────────────────────────────────────────────────────

    private function createEnvironment(
        string $companyName,
        string $adminName,
        string $adminEmail,
        Plan $plan,
        string $tenantStatus,
        string $subscriptionStatus,
        ?string $billingCycle,
        ?Order $order,
        ?int $trialDays,
    ): ProvisioningResult {
        $tenant = Tenant::create([
            'name'            => $companyName,
            'slug'            => $this->uniqueSlug($companyName),
            'status'          => $tenantStatus,
            'plan'            => $plan->slug,   // cache dénormalisé (source = subscription)
            'max_employees'   => $plan->max_employees ?? 100,
            'demo_expires_at' => $trialDays ? now()->addDays($trialDays) : null,
        ]);

        // company_admin SANS mot de passe exploitable : il le définira via l'activation.
        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name'      => $adminName,
            'email'     => $adminEmail,
            'role'      => 'company_admin',
            'password'  => Hash::make(Str::random(48)),
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'tenant_id'     => $tenant->id,
            'plan_id'       => $plan->id,
            'order_id'      => $order?->id,
            'status'        => $subscriptionStatus,
            'billing_cycle' => $billingCycle,
            'starts_at'     => now(),
            'trial_ends_at' => $trialDays ? now()->addDays($trialDays) : null,
        ]);

        $activationToken = $this->activations->issue($admin);

        return new ProvisioningResult($tenant, $admin, $subscription, true, $activationToken);
    }

    private function existingResult(Tenant $tenant): ProvisioningResult
    {
        $admin = $tenant->users()->where('role', 'company_admin')->first();

        return new ProvisioningResult($tenant, $admin, null, false, null);
    }

    private function uniqueSlug(string $companyName): string
    {
        $base = Str::slug($companyName) ?: 'tenant';
        $slug = $base;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base . '-' . Str::lower(Str::random(4));
        }

        return $slug;
    }
}
