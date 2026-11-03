<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->string('suspension_source', 16)->nullable()->after('status');
        });

        DB::table('listings')->where('status', 'SUSPENDED')
            ->update(['suspension_source' => 'ADMIN']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE listings ADD CONSTRAINT chk_listing_suspension_source CHECK ((status = 'SUSPENDED' AND suspension_source IS NOT NULL AND suspension_source IN ('CAREGIVER','ADMIN')) OR (status <> 'SUSPENDED' AND suspension_source IS NULL))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE listings DROP CHECK chk_listing_suspension_source');
        }

        Schema::table('listings', function (Blueprint $table): void {
            $table->dropColumn('suspension_source');
        });
    }
};
