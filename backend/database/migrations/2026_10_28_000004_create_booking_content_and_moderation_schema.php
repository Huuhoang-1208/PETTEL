<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->string('visibility', 16)->default('VISIBLE');
            $table->dateTime('created_at', 6);
            $table->index(['booking_id', 'created_at', 'id']);
        });

        Schema::create('care_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->text('body')->nullable();
            $table->foreignId('media_object_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('visibility', 16)->default('VISIBLE');
            $table->dateTime('created_at', 6);
            $table->index(['booking_id', 'created_at', 'id']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('listing_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('body', 1000)->nullable();
            $table->string('status', 16)->default('VISIBLE');
            $table->boolean('is_demo')->default(false);
            $table->timestamps(6);
            $table->index(['listing_id', 'status', 'is_demo', 'created_at', 'id']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('review_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reason_code', 64);
            $table->string('description', 2000)->nullable();
            $table->string('status', 16)->default('OPEN');
            $table->dateTime('resolved_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['status', 'created_at', 'id']);
            $table->index(['reporter_user_id', 'created_at', 'id']);
        });

        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->restrictOnDelete();
            $table->foreignId('admin_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 64);
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id');
            $table->text('reason');
            $table->dateTime('created_at', 6);
            $table->index(['report_id', 'created_at', 'id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE messages ADD CONSTRAINT chk_message_visibility CHECK (visibility IN ('VISIBLE','HIDDEN'))");
            DB::statement("ALTER TABLE care_updates ADD CONSTRAINT chk_care_update_visibility CHECK (visibility IN ('VISIBLE','HIDDEN'))");
            DB::statement('ALTER TABLE care_updates ADD CONSTRAINT chk_care_update_content CHECK (body IS NOT NULL OR media_object_id IS NOT NULL)');
            DB::statement('ALTER TABLE reviews ADD CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5)');
            DB::statement("ALTER TABLE reviews ADD CONSTRAINT chk_review_status CHECK (status IN ('VISIBLE','HIDDEN'))");
            DB::statement('ALTER TABLE reports ADD CONSTRAINT chk_report_one_target CHECK ((listing_id IS NOT NULL) + (review_id IS NOT NULL) + (message_id IS NOT NULL) + (booking_id IS NOT NULL) = 1)');
            DB::statement("ALTER TABLE reports ADD CONSTRAINT chk_report_status CHECK (status IN ('OPEN','IN_REVIEW','RESOLVED','DISMISSED'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('care_updates');
        Schema::dropIfExists('messages');
    }
};
