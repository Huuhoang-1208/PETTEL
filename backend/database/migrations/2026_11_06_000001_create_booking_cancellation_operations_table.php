<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['status', 'payment_due_at', 'id'], 'bookings_payment_expiry_idx');
            $table->index(['status', 'created_at', 'id'], 'bookings_request_expiry_created_idx');
            $table->index(['status', 'start_at', 'id'], 'bookings_request_expiry_start_idx');
        });

        Schema::create('booking_cancellation_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source', 16);
            $table->string('reason_code', 64);
            $table->text('reason_note')->nullable();
            $table->string('idempotency_key', 128)->nullable();
            $table->char('payload_hash', 64)->nullable();
            $table->string('gateway_operation_key', 64)->unique();
            $table->string('operation_type', 16);
            $table->string('status', 24)->default('PENDING');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->dateTime('next_retry_at', 6)->nullable();
            $table->dateTime('lease_expires_at', 6)->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->dateTime('completed_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique(
                ['booking_id', 'actor_user_id', 'idempotency_key'],
                'booking_cancel_actor_key_uq'
            );
            $table->index(['status', 'next_retry_at', 'lease_expires_at', 'id'], 'booking_cancel_due_idx');
            $table->index(['booking_id', 'status', 'id'], 'booking_cancel_booking_status_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE booking_cancellation_operations ADD CONSTRAINT chk_booking_cancel_source CHECK (source IN ('USER','SYSTEM','ADMIN'))");
            DB::statement("ALTER TABLE booking_cancellation_operations ADD CONSTRAINT chk_booking_cancel_type CHECK (operation_type IN ('NONE','VOID','REFUND'))");
            DB::statement("ALTER TABLE booking_cancellation_operations ADD CONSTRAINT chk_booking_cancel_status CHECK (status IN ('PENDING','PROCESSING','COMPLETED','MANUAL_REVIEW'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_cancellation_operations');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_payment_expiry_idx');
            $table->dropIndex('bookings_request_expiry_created_idx');
            $table->dropIndex('bookings_request_expiry_start_idx');
        });
    }
};
