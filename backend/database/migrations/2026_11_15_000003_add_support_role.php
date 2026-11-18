<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE user_roles DROP CHECK chk_user_roles_role');
        DB::statement("ALTER TABLE user_roles ADD CONSTRAINT chk_user_roles_role CHECK (role IN ('OWNER','CAREGIVER','ADMIN','SUPPORT'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('user_roles')->where('role', 'SUPPORT')->delete();
        DB::statement('ALTER TABLE user_roles DROP CHECK chk_user_roles_role');
        DB::statement("ALTER TABLE user_roles ADD CONSTRAINT chk_user_roles_role CHECK (role IN ('OWNER','CAREGIVER','ADMIN'))");
    }
};
