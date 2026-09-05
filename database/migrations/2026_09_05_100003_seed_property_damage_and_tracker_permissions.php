<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ajoute les permissions des dommages matériels (property_damage.*) et de la remise
 * à zéro du compteur de sécurité (safety_tracker.manage), puis réaligne les 5 rôles
 * sur la matrice App\Support\Permissions.
 *
 * Idempotente et re-jouable — même mécanisme que 2026_08_26_100000 : findOrCreate
 * puis syncPermissions. Aucune donnée métier touchée.
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

        $removed = [
            'property_damage.view', 'property_damage.create', 'property_damage.update',
            'property_damage.close', 'property_damage.delete', 'safety_tracker.manage',
        ];

        foreach (Permissions::ROLES as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()
                ?->revokePermissionTo(array_filter($removed, fn ($p) => Permission::where('name', $p)->where('guard_name', 'web')->exists()));
        }

        Permission::whereIn('name', $removed)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
