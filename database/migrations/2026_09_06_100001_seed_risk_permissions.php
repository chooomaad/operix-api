<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ajoute les permissions du module « management des risques » (risks.*) et réaligne
 * les rôles sur la matrice App\Support\Permissions. Idempotente et re-jouable.
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

        $removed = ['risks.view', 'risks.create', 'risks.update', 'risks.validate', 'risks.delete'];
        foreach (Permissions::ROLES as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()
                ?->revokePermissionTo(array_filter($removed, fn ($p) => Permission::where('name', $p)->where('guard_name', 'web')->exists()));
        }
        Permission::whereIn('name', $removed)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
