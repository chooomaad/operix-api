<?php

namespace App\Providers;

use App\Models\Breach;
use App\Models\Contractor;
use App\Models\Employee;
use App\Models\EnvironmentReport;
use App\Models\Equipment;
use App\Models\PermitToWork;
use App\Models\SafetyIncident;
use App\Models\SafetyNearMiss;
use App\Models\Visitor;
use App\Policies\BreachPolicy;
use App\Policies\ContractorPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\EnvironmentPolicy;
use App\Policies\EquipmentPolicy;
use App\Policies\IncidentPolicy;
use App\Policies\NearMissPolicy;
use App\Policies\PermitToWorkPolicy;
use App\Policies\VisitorPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Contexte tenant de la requête (renseigné par le middleware ResolveTenant).
        $this->app->singleton(\App\Support\TenantContext::class);

        // Prestataire de paiement actif (aucun provider réel — 'fake' par défaut).
        $this->app->singleton(\App\Payments\PaymentProvider::class, function ($app) {
            $provider = config('operix.payment.provider', 'fake');

            // GARDE-FOU : le provider fictif ne doit JAMAIS être actif en production
            // (sinon un faux paiement pourrait être accepté comme réel). Fail-closed.
            if ($provider === 'fake' && $app->environment('production')) {
                throw new \RuntimeException(
                    'FakePaymentProvider interdit en production. Configurez un vrai prestataire via OPERIX_PAYMENT_PROVIDER.'
                );
            }

            return match ($provider) {
                default => new \App\Payments\FakePaymentProvider(),
            };
        });
    }

    public function boot(): void
    {
        // Derrière le proxy TLS de Render, Laravel génère des URL en http (mixed
        // content côté HTTPS). On force le https en prod (ou si APP_URL est https).
        if ($this->app->environment('production')
            || str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        Gate::policy(Employee::class,        EmployeePolicy::class);
        Gate::policy(SafetyIncident::class,  IncidentPolicy::class);
        Gate::policy(SafetyNearMiss::class,  NearMissPolicy::class);
        Gate::policy(Breach::class,          BreachPolicy::class);
        Gate::policy(EnvironmentReport::class, EnvironmentPolicy::class);
        Gate::policy(Visitor::class,         VisitorPolicy::class);
        Gate::policy(Contractor::class,      ContractorPolicy::class);
        Gate::policy(Equipment::class,       EquipmentPolicy::class);
        Gate::policy(PermitToWork::class,    PermitToWorkPolicy::class);

        // Audit automatique (qui / quoi / quand) des modeles marques Auditable.
        // Enregistre ici (et non via static::observe() dans un boot de trait, qui
        // provoquerait un boot re-entrant).
        foreach ([
            \App\Models\SafetyIncident::class,    \App\Models\SafetyNearMiss::class,
            \App\Models\EnvironmentReport::class, \App\Models\Breach::class,
            \App\Models\Employee::class,          \App\Models\User::class,
            \App\Models\Visitor::class,           \App\Models\Contractor::class,
            \App\Models\Equipment::class,         \App\Models\Department::class,
            \App\Models\Formation::class,         \App\Models\Certification::class,
            \App\Models\MedicalVisit::class,      \App\Models\Intern::class,
        ] as $auditable) {
            $auditable::observe(\App\Observers\AuditObserver::class);
        }
    }
}
