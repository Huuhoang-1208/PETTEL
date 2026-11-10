<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_release_operations', function (Blueprint $table): void {
            $table->string('source', 16)->default('USER');
            $table->string('reason_code', 64)->default('OWNER_CONFIRMED');
            $table->text('reason_note')->nullable();
            $table->foreignId('report_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::table('booking_cancellation_operations', function (Blueprint $table): void {
            $table->foreignId('report_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_cancellation_operations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('report_id');
        });

        Schema::table('booking_release_operations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('report_id');
            $table->dropColumn(['source', 'reason_code', 'reason_note']);
        });
    }
};
