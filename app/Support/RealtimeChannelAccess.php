<?php

namespace App\Support;

use App\Models\User;

/**
 * Regle d'habilitation commune aux canaux temps reel.
 *
 * Extraite de routes/channels.php pour une seule raison technique, mais qui
 * compte : le fichier de canaux est re-`require` dans les tests pour reenregistrer
 * les regles sur un diffuseur reel. Une fonction declaree dans ce fichier serait
 * alors redeclaree — erreur fatale. Une methode statique, elle, se recharge sans
 * heurt. La regle reste ainsi unique et partagee par les deux canaux.
 */
final class RealtimeChannelAccess
{
    /**
     * Un utilisateur authentifie est-il encore reellement habilite ?
     *
     * Actif, rattache a un tenant, et ce tenant lui-meme actif. Un jeton Sanctum
     * survit a une desactivation de compte ou a une suspension d'entreprise : on
     * revalide donc l'etat a chaque abonnement, sinon un acces revoque continuerait
     * de recevoir les evenements via un onglet reste ouvert.
     */
    public static function usable(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $tenant = $user->tenant;

        return $tenant !== null && $tenant->status === 'active';
    }
}
