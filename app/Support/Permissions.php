<?php

namespace App\Support;

/**
 * Matrice des permissions applicatives Operix.
 *
 * SOURCE DE VÉRITÉ de l'autorisation métier. Jusqu'ici, les droits étaient exprimés
 * uniquement par des listes de rôles recopiées dans `routes/api.php`
 * (`role:company_admin,hsse_manager,...`) : la règle était éparpillée sur 396 lignes,
 * impossible à exposer à un client, et les 10 policies déclarées n'étaient jamais
 * appelées. Voir docs/MOBILE_API_READINESS.md §B5.
 *
 * Granularité : fine sur les trois modules de terrain (incidents, presqu'accidents,
 * environnement), où le droit de *signaler* et le droit de *clôturer* n'appartiennent
 * délibérément pas aux mêmes personnes ; grossière (`.manage`) ailleurs, où toutes les
 * opérations d'un module partagent le même public. On ne crée pas de distinction que
 * l'application ne fait pas.
 *
 * Le rôle plateforme `super_admin` ne figure volontairement dans AUCUNE permission
 * métier : il n'appartient à aucun tenant et EnsureTenantContext le rejette sur les
 * routes métier. Ses droits passent exclusivement par /superadmin/*.
 */
class Permissions
{
    public const ROLES = ['super_admin', 'company_admin', 'hsse_manager', 'supervisor', 'agent'];

    /** Rôles disposant d'un accès applicatif (hors plateforme). */
    public const APPLICATION_ROLES = ['company_admin', 'hsse_manager', 'supervisor', 'agent'];

    private const CA = 'company_admin';
    private const HM = 'hsse_manager';
    private const SV = 'supervisor';
    private const AG = 'agent';

    /**
     * permission => rôles qui la détiennent.
     *
     * Cette matrice reproduit fidèlement le gating par rôle existant, à une exception
     * explicitement arbitrée : `near_miss.create` et `environment.create` sont ouverts
     * au superviseur et à l'agent. Un presqu'accident n'a de valeur statistique que s'il
     * est massivement signalé par le terrain, et c'est la transposition directe de la
     * règle déjà retenue pour les incidents (signaler oui, clôturer non).
     */
    public const MATRIX = [
        // ── Transverse : tout utilisateur authentifié d'un tenant ─────────────────
        'dashboard.view'      => [self::CA, self::HM, self::SV, self::AG],
        'search.use'          => [self::CA, self::HM, self::SV, self::AG],
        'media.upload'        => [self::CA, self::HM, self::SV, self::AG],
        'notifications.view'  => [self::CA, self::HM, self::SV, self::AG],
        'notifications.send'  => [self::CA, self::HM],

        // ── Incidents ─────────────────────────────────────────────────────────────
        'incidents.view'      => [self::CA, self::HM, self::SV, self::AG],
        'incidents.create'    => [self::CA, self::HM, self::SV, self::AG],
        'incidents.update'    => [self::CA, self::HM, self::SV],
        'incidents.close'     => [self::CA, self::HM],
        'incidents.delete'    => [self::CA, self::HM],

        // ── Presqu'accidents ──────────────────────────────────────────────────────
        'near_miss.view'      => [self::CA, self::HM, self::SV, self::AG],
        'near_miss.create'    => [self::CA, self::HM, self::SV, self::AG],
        'near_miss.update'    => [self::CA, self::HM, self::SV],
        'near_miss.close'     => [self::CA, self::HM],
        'near_miss.delete'    => [self::CA, self::HM],

        // ── Environnement ─────────────────────────────────────────────────────────
        'environment.view'    => [self::CA, self::HM, self::SV, self::AG],
        'environment.create'  => [self::CA, self::HM, self::SV, self::AG],
        'environment.update'  => [self::CA, self::HM, self::SV],
        'environment.close'   => [self::CA, self::HM],
        'environment.delete'  => [self::CA, self::HM],

        // ── Personnel & référentiels ──────────────────────────────────────────────
        'employees.view'      => [self::CA, self::HM, self::SV, self::AG],
        'employees.manage'    => [self::CA, self::HM],
        'employees.pii.view'  => [self::CA, self::HM],
        'departments.view'    => [self::CA, self::HM, self::SV, self::AG],
        'departments.manage'  => [self::CA],
        'formations.manage'   => [self::CA, self::HM],
        'certifications.manage' => [self::CA, self::HM],
        'medical_visits.manage' => [self::CA, self::HM],

        // ── Modules d'encadrement ─────────────────────────────────────────────────
        'gemba.manage'        => [self::CA, self::HM],
        'breaches.manage'     => [self::CA, self::HM],
        'visitors.manage'     => [self::CA, self::HM, self::SV],
        'contractors.manage'  => [self::CA, self::HM],
        'equipment.manage'    => [self::CA, self::HM],
        'permits.manage'      => [self::CA, self::HM],
        'safety_tracker.view' => [self::CA, self::HM, self::SV],

        // ── Restitution & administration ──────────────────────────────────────────
        'reports.generate'    => [self::CA, self::HM, self::SV],
        'exports.generate'    => [self::CA, self::HM, self::SV],
        'imports.run'         => [self::CA, self::HM],
        'audit.view'          => [self::CA, self::HM],
        'users.manage'        => [self::CA],
        'settings.manage'     => [self::CA],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::MATRIX);
    }

    /**
     * Permissions attachées à un rôle donné.
     *
     * @return list<string>
     */
    public static function forRole(string $role): array
    {
        return array_values(array_keys(
            array_filter(self::MATRIX, fn (array $roles) => in_array($role, $roles, true))
        ));
    }

    /**
     * Rôles applicatifs qui détiennent une permission donnée.
     *
     * L'inverse de {@see forRole()} : sert à résoudre les destinataires d'une
     * notification (« qui a le droit de voir un incident ? ») sans dépendre de
     * l'état des tables Spatie. `super_admin` n'apparaît dans aucune ligne de la
     * matrice — il contourne les permissions — et n'est donc jamais destinataire
     * métier ici, ce qui est le comportement voulu.
     *
     * @return list<string>
     */
    public static function rolesFor(string $permission): array
    {
        return array_values(self::MATRIX[$permission] ?? []);
    }
}
