<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Certification;
use App\Models\Formation;
use App\Models\GembaWalk;
use App\Models\MedicalVisit;
use App\Models\PermitToWork;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SendExpiryNotifications extends Command
{
    protected $signature   = 'operix:expiry-notifications {--days=30 : Notify X days before expiry}';
    protected $description = 'Send notifications for expiring certifications, medical visits, training, and permits';

    public function handle(): int
    {
        $days      = (int) $this->option('days');
        $threshold = now()->addDays($days)->toDateString();
        $today     = now()->toDateString();

        $this->info("Vérification des expirations dans les {$days} prochains jours...");

        $adminIds = User::where('is_active', true)->where('role', 'admin')->pluck('id');

        if ($adminIds->isEmpty()) {
            $this->warn('Aucun administrateur actif trouvé.');
            return self::SUCCESS;
        }

        $this->checkCertifications($adminIds, $threshold, $today);
        $this->checkMedicalVisits($adminIds, $threshold, $today);
        $this->checkFormations($adminIds, $threshold, $today);
        $this->checkPermits($adminIds, $threshold, $today);
        $this->checkGembaDeadlines($adminIds, $today);

        $this->info('Notifications d\'expiration envoyées.');
        return self::SUCCESS;
    }

    private function checkCertifications($adminIds, string $threshold, string $today): void
    {
        // Expirant bientôt
        $expiring = Certification::query()
            ->with('employee:id,prenom,nom,matricule')
            ->where('statut', 'valid')
            ->whereNotNull('date_expiration')
            ->whereDate('date_expiration', '<=', $threshold)
            ->whereDate('date_expiration', '>=', $today)
            ->get();

        foreach ($expiring as $cert) {
            $daysLeft = now()->diffInDays($cert->date_expiration);
            $this->notify($adminIds, 'warning', [
                'title'    => "Certification expirante — {$cert->employee?->full_name}",
                'body'     => "La certification «{$cert->type}» de {$cert->employee?->full_name} ({$cert->employee?->matricule}) expire dans {$daysLeft} jour(s).",
                'type'     => 'certification_expiry',
                'model'    => 'certification',
                'model_id' => $cert->id,
            ]);
        }

        // Déjà expirées → mettre à jour statut
        Certification::query()
            ->where('statut', 'valid')
            ->whereNotNull('date_expiration')
            ->whereDate('date_expiration', '<', $today)
            ->update(['statut' => 'expired']);
    }

    private function checkMedicalVisits($adminIds, string $threshold, string $today): void
    {
        $expiring = MedicalVisit::query()
            ->with('employee:id,prenom,nom,matricule')
            ->whereNotNull('prochaine_visite')
            ->whereDate('prochaine_visite', '<=', $threshold)
            ->whereDate('prochaine_visite', '>=', $today)
            ->get();

        foreach ($expiring as $visit) {
            $daysLeft = now()->diffInDays($visit->prochaine_visite);
            $this->notify($adminIds, 'warning', [
                'title'    => "Visite médicale à planifier — {$visit->employee?->full_name}",
                'body'     => "La visite médicale de {$visit->employee?->full_name} ({$visit->employee?->matricule}) est à renouveler dans {$daysLeft} jour(s).",
                'type'     => 'medical_visit_due',
                'model'    => 'medical_visit',
                'model_id' => $visit->id,
            ]);
        }
    }

    private function checkFormations($adminIds, string $threshold, string $today): void
    {
        $expiring = Formation::query()
            ->with('employee:id,prenom,nom,matricule')
            ->whereNotNull('date_fin')
            ->where('statut', '!=', 'terminee')
            ->whereDate('date_fin', '<=', $threshold)
            ->whereDate('date_fin', '>=', $today)
            ->get();

        foreach ($expiring as $formation) {
            $daysLeft = now()->diffInDays($formation->date_fin);
            $this->notify($adminIds, 'info', [
                'title'    => "Formation se terminant bientôt — {$formation->employee?->full_name}",
                'body'     => "La formation «{$formation->titre}» de {$formation->employee?->full_name} se termine dans {$daysLeft} jour(s).",
                'type'     => 'formation_ending',
                'model'    => 'formation',
                'model_id' => $formation->id,
            ]);
        }
    }

    private function checkPermits($adminIds, string $threshold, string $today): void
    {
        $expiring = PermitToWork::query()
            ->whereIn('status', ['approved', 'active'])
            ->whereNotNull('valid_to')
            ->whereDate('valid_to', '<=', $threshold)
            ->whereDate('valid_to', '>=', $today)
            ->get();

        foreach ($expiring as $permit) {
            $daysLeft = now()->diffInDays($permit->valid_to);
            $this->notify($adminIds, 'warning', [
                'title'    => "Permis de travail expirant — {$permit->reference}",
                'body'     => "Le permis «{$permit->reference}» ({$permit->title}) expire dans {$daysLeft} jour(s).",
                'type'     => 'permit_expiry',
                'model'    => 'permit',
                'model_id' => $permit->id,
            ]);
        }

        // Expirés → fermer automatiquement
        PermitToWork::query()
            ->whereIn('status', ['approved', 'active'])
            ->whereDate('valid_to', '<', $today)
            ->update(['status' => 'expired']);
    }

    private function checkGembaDeadlines($adminIds, string $today): void
    {
        $threshold3 = now()->addDays(3)->toDateString();

        // Fiches en retard (non résolues, deadline dépassée)
        $overdue = GembaWalk::query()
            ->where('status', '!=', 'resolved')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->get();

        foreach ($overdue as $walk) {
            $daysLate = now()->diffInDays($walk->due_date);
            $this->notify($adminIds, 'error', [
                'title'    => "⚠️ Gemba en retard — {$walk->zone}",
                'body'     => "La fiche Gemba «{$walk->observation}» (zone : {$walk->zone}) est en retard de {$daysLate} jour(s). Responsable : {$walk->responsible}.",
                'type'     => 'gemba_overdue',
                'model'    => 'gemba_walk',
                'model_id' => $walk->id,
                'link'     => '/gemba-walks',
            ]);
        }

        // Fiches dont la deadline est dans les 3 prochains jours
        $dueSoon = GembaWalk::query()
            ->where('status', '!=', 'resolved')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $threshold3)
            ->get();

        foreach ($dueSoon as $walk) {
            $daysLeft = now()->diffInDays($walk->due_date);
            $this->notify($adminIds, 'warning', [
                'title'    => "🕐 Gemba — deadline dans {$daysLeft} jour(s)",
                'body'     => "La fiche Gemba «{$walk->observation}» (zone : {$walk->zone}) doit être résolue avant le {$walk->due_date}. Responsable : {$walk->responsible}.",
                'type'     => 'gemba_due_soon',
                'model'    => 'gemba_walk',
                'model_id' => $walk->id,
                'link'     => '/gemba-walks',
            ]);
        }
    }

    private function notify($userIds, string $type, array $data): void
    {
        foreach ($userIds as $userId) {
            $alreadySent = AppNotification::where('notifiable_id', $userId)
                ->whereDate('created_at', today())
                ->where('data->type', $data['type'])
                ->where('data->model_id', $data['model_id'] ?? null)
                ->exists();

            if ($alreadySent) continue;

            AppNotification::create([
                'id'              => Str::uuid(),
                'type'            => $type,
                'notifiable_type' => User::class,
                'notifiable_id'   => $userId,
                'data'            => $data,
            ]);
        }
    }
}
