<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_release_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->constrained()->restrictOnDelete();
            $table->uuid('gateway_operation_key')->unique();
            $table->string('status', 24)->default('PENDING');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->dateTime('next_retry_at', 6)->nullable();
            $table->dateTime('lease_expires_at', 6)->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->dateTime('completed_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique(
                ['booking_id', 'payment_attempt_id'],
                'booking_release_booking_attempt_uq',
            );
            $table->index(
                ['status', 'next_retry_at', 'lease_expires_at', 'id'],
                'booking_release_due_idx',
            );
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE booking_release_operations ADD CONSTRAINT chk_booking_release_status CHECK (status IN ('PENDING','PROCESSING','COMPLETED','MANUAL_REVIEW'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_release_operations');
    }
};
