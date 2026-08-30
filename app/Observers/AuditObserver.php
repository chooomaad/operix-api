<?php

namespace App\Observers;

use App\Events\NotificationSent;
use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\User;
use App\Support\Permissions;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Journalise automatiquement TOUTE creation / modification / suppression des
 * modeles marques `Auditable` : qui (utilisateur authentifie), quoi (modele +
 * changements), quand (created_at). Notifie en outre les responsables (audit.view)
 * sur les actions importantes.
 *
 * ROBUSTE : toute la journalisation est encapsulee dans un try/catch — un echec
 * d'audit ne doit JAMAIS casser l'operation metier qui l'a declenche.
 */
class AuditObserver
{
    /** Nom de modele -> libelle stable pour l'action (« incident_created »…). */
    private const LABELS = [
        \App\Models\SafetyIncident::class    => 'incident',
        \App\Models\SafetyNearMiss::class    => 'near_miss',
        \App\Models\EnvironmentReport::class => 'environment',
        \App\Models\Breach::class            => 'breach',
        \App\Models\Employee::class          => 'employee',
        \App\Models\User::class              => 'user',
        \App\Models\Visitor::class           => 'visitor',
        \App\Models\Contractor::class        => 'contractor',
        \App\Models\Equipment::class         => 'equipment',
        \App\Models\Department::class         => 'department',
    ];

    /** Champs jamais recopies dans le journal (secrets). */
    private const REDACT = [
        'password', 'remember_token', 'token', 'token_hash', 'secret',
        'pin', 'api_key', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    /**
     * Champs dont la modification SEULE ne merite pas une entree d'audit (bruit) :
     * ex. l'horodatage de derniere connexion, mis a jour a chaque login.
     */
    private const IGNORE_ONLY = ['last_login_at', 'updated_at', 'last_modified_at', 'last_modified_by'];

    public function created(Model $model): void { $this->record('created', $model); }
    public function updated(Model $model): void { $this->record('updated', $model); }
    public function deleted(Model $model): void { $this->record('deleted', $model); }

    private function record(string $verb, Model $model): void
    {
        try {
            $actor = request()?->user();

            // Pas d'utilisateur authentifie (seed, job systeme, console) : ce n'est
            // pas une action utilisateur auditable. On s'abstient plutot que de
            // polluer le journal avec des lignes « par personne ».
            if (! $actor) {
                return;
            }

            $label = self::LABELS[$model::class] ?? Str::snake(class_basename($model));

            [$old, $new] = $this->diff($verb, $model);

            // Modification qui ne touche que des champs « bruit » : on n'audite pas.
            if ($verb === 'updated' && $this->onlyNoise($new)) {
                return;
            }

            ActivityLog::create([
                'user_id'    => $actor->id,
                'action'     => "{$label}_{$verb}",
                'model_type' => $model::class,
                'model_id'   => (int) $model->getKey(),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 255, ''),
            ]);

            $this->maybeNotify($verb, $label, $model, $actor);
        } catch (\Throwable $e) {
            // L'audit ne doit jamais faire echouer l'action metier.
            report($e);
        }
    }

    /**
     * @return array{0: array<string,mixed>|null, 1: array<string,mixed>|null}
     */
    private function diff(string $verb, Model $model): array
    {
        if ($verb === 'created') {
            return [null, $this->clean($model->getAttributes())];
        }
        if ($verb === 'deleted') {
            return [$this->clean($model->getOriginal()), null];
        }
        // updated : uniquement les champs modifies, avant/apres.
        $changes = $this->clean($model->getChanges());
        $before  = [];
        foreach (array_keys($changes) as $k) {
            $before[$k] = $model->getOriginal($k);
        }
        return [$this->clean($before), $changes];
    }

    /** Retire les champs secrets d'un tableau d'attributs. */
    private function clean(array $attrs): array
    {
        foreach (self::REDACT as $field) {
            if (array_key_exists($field, $attrs)) {
                $attrs[$field] = '****';
            }
        }
        return $attrs;
    }

    private function onlyNoise(?array $changes): bool
    {
        if (empty($changes)) {
            return true;
        }
        return count(array_diff(array_keys($changes), self::IGNORE_ONLY)) === 0;
    }

    /**
     * Notifie les detenteurs d'audit.view sur les actions IMPORTANTES.
     *
     * On exclut la CREATION des trois modules HSE (incident/near-miss/environnement),
     * deja notifiee aux habilites par SendHseEventNotifications — pour ne pas envoyer
     * deux notifications pour le meme evenement. Les mises a jour ne notifient pas
     * (trop de bruit) : elles restent dans le journal.
     */
    private function maybeNotify(string $verb, string $label, Model $model, User $actor): void
    {
        if ($verb === 'updated') {
            return;
        }
        if ($verb === 'created' && in_array($label, ['incident', 'near_miss', 'environment'], true)) {
            return; // deja notifie par le flux HSE
        }

        $tenantId = (int) ($model->getAttribute('tenant_id') ?? app(TenantContext::class)->id() ?? 0);
        if ($tenantId === 0) {
            return;
        }

        $roles = Permissions::rolesFor('audit.view');
        $recipients = User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('role', $roles)
            ->where('id', '!=', $actor->id)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $data = [
            'title'         => $this->title($verb, $label),
            'body'          => $actor->name . ' · ' . now()->format('d/m H:i'),
            'type'          => $verb === 'deleted' ? 'warning' : 'info',
            'resource_kind' => $label,
            'resource_id'   => (int) $model->getKey(),
            'action'        => "{$label}_{$verb}",
            'link'          => '/audit',
        ];

        $context  = app(TenantContext::class);
        $previous = $context->id();
        $context->set($tenantId);
        try {
            foreach ($recipients as $recipient) {
                $notification = AppNotification::create([
                    'id'              => (string) Str::uuid(),
                    'type'            => $data['type'],
                    'notifiable_type' => User::class,
                    'notifiable_id'   => $recipient->id,
                    'data'            => $data,
                ]);
                NotificationSent::dispatch($notification, $recipient->id);
            }
        } finally {
            $context->set($previous);
        }
    }

    private function title(string $verb, string $label): string
    {
        $verbe = match ($verb) {
            'created' => 'cree',
            'deleted' => 'supprime',
            default   => 'modifie',
        };
        $noun = match ($label) {
            'employee'    => 'Employe',
            'user'        => 'Compte utilisateur',
            'visitor'     => 'Visiteur',
            'contractor'  => 'Prestataire',
            'equipment'   => 'Equipement',
            'breach'      => 'Infraction',
            'department'  => 'Departement',
            'incident'    => 'Incident',
            'near_miss'   => 'Presqu accident',
            'environment' => 'Rapport environnement',
            default       => ucfirst($label),
        };
        return "{$noun} {$verbe}";
    }
}
