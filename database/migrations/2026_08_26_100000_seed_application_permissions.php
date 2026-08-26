<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crée les permissions applicatives et les attache aux 5 rôles.
 *
 * Spatie était installé depuis la Phase 2 mais AUCUNE permission n'avait jamais été
 * définie : l'autorisation reposait entièrement sur des listes de rôles inscrites dans
 * les routes. Cette migration matérialise la matrice App\Support\Permissions.
 *
 * Idempotente et re-jouable : `findOrCreate` puis `syncPermissions` — rejouer la
 * migration réaligne les rôles sur la matrice sans dupliquer de lignes. Aucune donnée
 * métier n'est touchée.
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
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // On détache avant de supprimer pour ne pas laisser de lignes de pivot orphelines.
        foreach (Permissions::ROLES as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->syncPermissions([]);
        }

        Permission::whereIn('name', Permissions::all())->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
