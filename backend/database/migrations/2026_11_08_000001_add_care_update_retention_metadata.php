<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_updates', function (Blueprint $table): void {
            $table->dateTime('redacted_at', 6)->nullable()->after('created_at');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE care_updates DROP CHECK chk_care_update_content');
            DB::statement('ALTER TABLE care_updates ADD CONSTRAINT chk_care_update_content CHECK (body IS NOT NULL OR media_object_id IS NOT NULL OR redacted_at IS NOT NULL)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('care_updates')->whereNotNull('redacted_at')->whereNull('body')->whereNull('media_object_id')
                ->update(['body' => '[redacted by retention policy]', 'redacted_at' => null]);
            DB::statement('ALTER TABLE care_updates DROP CHECK chk_care_update_content');
            DB::statement('ALTER TABLE care_updates ADD CONSTRAINT chk_care_update_content CHECK (body IS NOT NULL OR media_object_id IS NOT NULL)');
        }

        Schema::table('care_updates', function (Blueprint $table): void {
            $table->dropColumn('redacted_at');
        });
    }
};
