<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->json('application_data_json')->nullable()->after('area_code');
        });
    }

    public function down(): void
    {
        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->dropColumn('application_data_json');
        });
    }
};
