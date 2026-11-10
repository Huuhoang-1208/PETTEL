<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_cancellation_operations', function (Blueprint $table): void {
            $table->string('booking_status_before', 32)->nullable()->after('report_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_cancellation_operations', function (Blueprint $table): void {
            $table->dropColumn('booking_status_before');
        });
    }
};
