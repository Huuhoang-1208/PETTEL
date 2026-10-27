<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('pet_id')->constrained()->restrictOnDelete();
            $table->foreignId('listing_id')->constrained()->restrictOnDelete();
            $table->foreignId('caregiver_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('start_at', 6);
            $table->dateTime('end_at', 6);
            $table->string('status', 32)->default('REQUESTED');
            $table->unsignedBigInteger('price_snapshot_vnd');
            $table->string('price_unit', 16);
            $table->unsignedSmallInteger('price_quantity');
            $table->unsignedSmallInteger('pricing_version')->default(1);
            $table->unsignedSmallInteger('cancellation_policy_version')->default(1);
            $table->json('pet_snapshot_json');
            $table->json('listing_snapshot_json');
            $table->json('policy_snapshot_json');
            $table->text('instructions_snapshot')->nullable();
            $table->dateTime('privacy_redacted_at', 6)->nullable();
            $table->dateTime('payment_due_at', 6)->nullable();
            $table->unsignedBigInteger('current_payment_attempt_id')->nullable();
            $table->string('request_idempotency_key', 128);
            $table->char('request_payload_hash', 64);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps(6);
            $table->unique(['owner_id', 'request_idempotency_key'], 'bookings_owner_request_key_uq');
            $table->index(['listing_id', 'status', 'start_at', 'end_at'], 'bookings_listing_conflict_idx');
            $table->index(['owner_id', 'created_at', 'id'], 'bookings_owner_timeline_idx');
            $table->index(['caregiver_user_id', 'status', 'start_at', 'id'], 'bookings_caregiver_timeline_idx');
        });

        Schema::create('booking_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 16);
            $table->string('reason_code', 64)->nullable();
            $table->text('reason_text')->nullable();
            $table->dateTime('created_at', 6);
            $table->index(['booking_id', 'created_at', 'id']);
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->unsignedBigInteger('amount_vnd');
            $table->string('status', 16)->default('PENDING');
            $table->string('provider_ref', 191)->nullable()->unique();
            $table->string('idempotency_key', 128);
            $table->char('request_payload_hash', 64);
            $table->dateTime('authorized_at', 6)->nullable();
            $table->dateTime('released_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['booking_id', 'attempt_no']);
            $table->unique(['booking_id', 'idempotency_key']);
            $table->index(['booking_id', 'status', 'id']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_attempt_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('provider', 64);
            $table->string('external_event_id', 191);
            $table->string('event_type', 64);
            $table->char('payload_hash', 64);
            $table->string('processing_status', 24);
            $table->string('rejection_code', 64)->nullable();
            $table->dateTime('received_at', 6);
            $table->dateTime('processed_at', 6)->nullable();
            $table->unique(['provider', 'external_event_id'], 'payment_events_provider_external_uq');
            $table->index(['payment_attempt_id', 'received_at', 'id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('current_payment_attempt_id', 'bookings_current_payment_attempt_fk')
                ->references('id')->on('payment_attempts')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE bookings ADD CONSTRAINT chk_booking_range CHECK (start_at < end_at)');
            DB::statement('ALTER TABLE bookings ADD CONSTRAINT chk_booking_price CHECK (price_snapshot_vnd > 0 AND price_quantity > 0)');
            DB::statement("ALTER TABLE bookings ADD CONSTRAINT chk_booking_price_unit CHECK (price_unit IN ('HOUR','NIGHT'))");
            DB::statement("ALTER TABLE bookings ADD CONSTRAINT chk_booking_status CHECK (status IN ('REQUESTED','AWAITING_PAYMENT','CONFIRMED','IN_PROGRESS','AWAITING_CONFIRMATION','COMPLETED','CANCELLED'))");
            DB::statement("ALTER TABLE booking_status_events ADD CONSTRAINT chk_booking_event_source CHECK (source IN ('USER','SYSTEM','SANDBOX','ADMIN'))");
            DB::statement("ALTER TABLE booking_status_events ADD CONSTRAINT chk_booking_event_status CHECK (to_status IN ('REQUESTED','AWAITING_PAYMENT','CONFIRMED','IN_PROGRESS','AWAITING_CONFIRMATION','COMPLETED','CANCELLED') AND (from_status IS NULL OR from_status IN ('REQUESTED','AWAITING_PAYMENT','CONFIRMED','IN_PROGRESS','AWAITING_CONFIRMATION','COMPLETED','CANCELLED')))");
            DB::statement("ALTER TABLE payment_attempts ADD CONSTRAINT chk_payment_attempt_status CHECK (status IN ('PENDING','FAILED','AUTHORIZED','VOIDED','REFUNDED','RELEASED'))");
            DB::statement('ALTER TABLE payment_attempts ADD CONSTRAINT chk_payment_attempt_amount CHECK (amount_vnd > 0)');
            DB::statement("ALTER TABLE payment_events ADD CONSTRAINT chk_payment_event_status CHECK (processing_status IN ('RECEIVED','PROCESSED','REJECTED'))");
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign('bookings_current_payment_attempt_fk');
        });

        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('booking_status_events');
        Schema::dropIfExists('bookings');
    }
};
