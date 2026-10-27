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

        DB::statement("ALTER TABLE users MODIFY status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE'");
        DB::statement('ALTER TABLE user_roles MODIFY role VARCHAR(16) NOT NULL');
        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_status CHECK (status IN ('ACTIVE','SUSPENDED','DELETION_PENDING','DEACTIVATED'))");
        DB::statement("ALTER TABLE user_roles ADD CONSTRAINT chk_user_roles_role CHECK (role IN ('OWNER','CAREGIVER','ADMIN'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE user_roles DROP CHECK chk_user_roles_role');
        DB::statement('ALTER TABLE users DROP CHECK chk_users_status');
        DB::statement("ALTER TABLE user_roles MODIFY role ENUM('OWNER','CAREGIVER','ADMIN') NOT NULL");
        DB::statement("ALTER TABLE users MODIFY status ENUM('ACTIVE','SUSPENDED','DELETION_PENDING','DEACTIVATED') NOT NULL DEFAULT 'ACTIVE'");
    }
};
