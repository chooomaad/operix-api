<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Resynchronise les rôles sur la matrice après l'ajout de `employees.agent_search`
 * et le retrait de l'agent de `employees.view` (phase Agent).
 *
 * Rejoue exactement la logique de la migration de seed initiale : findOrCreate des
 * permissions + syncPermissions par rôle. Idempotente ; aucune donnée métier touchée.
 * Nécessaire pour appliquer le changement sur les bases déjà migrées (la migration
 * de seed d'origine ayant déjà tourné).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (Permissions::ROLES as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions(Permissions::forRole($roleName));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Réversibilité best-effort : on réaligne simplement sur la matrice courante.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::ROLES as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions(Permissions::forRole($roleName));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
