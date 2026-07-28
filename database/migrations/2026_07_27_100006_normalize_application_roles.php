<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        DB::table('users')->where('role', 'admin')->update(['role' => 'company_admin']);

        foreach (['super_admin', 'company_admin', 'hsse_manager', 'supervisor', 'agent'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        User::query()->whereNotNull('role')->each(function (User $user): void {
            $user->syncRoles([$user->role]);
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY['super_admin','company_admin','hsse_manager','supervisor','agent']::text[]))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        DB::table('users')->where('role', 'company_admin')->update(['role' => 'admin']);
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY['admin','agent','super_admin']::text[]))");
    }
};
