<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('object_key')->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->string('visibility', 24)->default('PRIVATE');
            $table->string('state', 24)->default('READY');
            $table->timestamps(6);
        });

        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('species', 16);
            $table->date('birth_date')->nullable();
            $table->string('age_note', 100)->nullable();
            $table->string('sex', 16)->nullable();
            $table->string('size', 16)->nullable();
            $table->text('diet_notes')->nullable();
            $table->text('vaccination_note')->nullable();
            $table->text('temperament')->nullable();
            $table->text('special_needs')->nullable();
            $table->string('status', 16)->default('ACTIVE');
            $table->foreignId('photo_media_id')->nullable()->constrained('media_objects')->nullOnDelete();
            $table->softDeletes('deleted_at', 6);
            $table->timestamps(6);
            $table->index(['user_id', 'deleted_at', 'id']);
        });

        Schema::create('caregiver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('type', 16);
            $table->string('display_name', 100);
            $table->text('bio')->nullable();
            $table->string('area_code', 32);
            $table->softDeletes('deleted_at', 6);
            $table->timestamps(6);
            $table->index('area_code');
        });

        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caregiver_profile_id')->constrained()->restrictOnDelete();
            $table->string('title', 120);
            $table->text('description');
            $table->string('service_type', 16);
            $table->string('area_code', 32);
            $table->unsignedBigInteger('price_vnd');
            $table->string('price_unit', 16);
            $table->unsignedTinyInteger('capacity')->default(1);
            $table->json('acceptance_rules_json');
            $table->string('status', 24)->default('DRAFT');
            $table->dateTime('published_at', 6)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->softDeletes('deleted_at', 6);
            $table->timestamps(6);
            $table->index(['status', 'service_type', 'area_code', 'price_vnd', 'id'], 'listings_public_search_idx');
            $table->index(['caregiver_profile_id', 'status', 'id'], 'listings_owner_status_idx');
        });

        Schema::create('availability_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->restrictOnDelete();
            $table->dateTime('start_at', 6);
            $table->dateTime('end_at', 6);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->softDeletes('deleted_at', 6);
            $table->timestamps(6);
            $table->index(['listing_id', 'start_at', 'end_at']);
        });

        Schema::create('listing_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('base_listing_version');
            $table->json('proposed_data_json');
            $table->string('status', 24)->default('PENDING_REVIEW');
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('decision_reason')->nullable();
            $table->dateTime('requested_at', 6);
            $table->dateTime('reviewed_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['listing_id', 'status', 'id']);
        });

        Schema::create('listing_media', function (Blueprint $table) {
            $table->foreignId('listing_id')->constrained()->restrictOnDelete();
            $table->foreignId('media_object_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->primary(['listing_id', 'media_object_id']);
            $table->unique(['listing_id', 'sort_order']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE media_objects ADD CONSTRAINT chk_media_byte_size CHECK (byte_size > 0)');
            DB::statement("ALTER TABLE media_objects ADD CONSTRAINT chk_media_visibility CHECK (visibility IN ('PRIVATE','PUBLIC_ELIGIBLE'))");
            DB::statement("ALTER TABLE media_objects ADD CONSTRAINT chk_media_state CHECK (state IN ('READY','PENDING_DELETE'))");
            DB::statement("ALTER TABLE pets ADD CONSTRAINT chk_pets_species CHECK (species IN ('DOG','CAT','OTHER'))");
            DB::statement("ALTER TABLE pets ADD CONSTRAINT chk_pets_sex CHECK (sex IS NULL OR sex IN ('MALE','FEMALE','UNKNOWN'))");
            DB::statement("ALTER TABLE pets ADD CONSTRAINT chk_pets_size CHECK (size IS NULL OR size IN ('SMALL','MEDIUM','LARGE'))");
            DB::statement("ALTER TABLE pets ADD CONSTRAINT chk_pets_status CHECK (status IN ('ACTIVE','ARCHIVED'))");
            DB::statement('ALTER TABLE pets ADD CONSTRAINT chk_pets_name_length CHECK (CHAR_LENGTH(name) BETWEEN 1 AND 100)');
            DB::statement("ALTER TABLE caregiver_profiles ADD CONSTRAINT chk_caregiver_type CHECK (type IN ('INDIVIDUAL','FACILITY'))");
            DB::statement("ALTER TABLE caregiver_profiles ADD CONSTRAINT chk_caregiver_area CHECK (area_code IN ('DN_CENTER','DN_NORTH','DN_SOUTH','DN_WEST','DN_EAST'))");
            DB::statement("ALTER TABLE listings ADD CONSTRAINT chk_listing_service CHECK ((service_type = 'DAYCARE' AND price_unit = 'HOUR') OR (service_type = 'BOARDING' AND price_unit = 'NIGHT'))");
            DB::statement("ALTER TABLE listings ADD CONSTRAINT chk_listing_area CHECK (area_code IN ('DN_CENTER','DN_NORTH','DN_SOUTH','DN_WEST','DN_EAST'))");
            DB::statement('ALTER TABLE listings ADD CONSTRAINT chk_listing_price_capacity CHECK (price_vnd > 0 AND capacity = 1)');
            DB::statement("ALTER TABLE listings ADD CONSTRAINT chk_listing_status CHECK (status IN ('DRAFT','PENDING_REVIEW','ACTIVE','SUSPENDED','ARCHIVED'))");
            DB::statement('ALTER TABLE availability_slots ADD CONSTRAINT chk_availability_range CHECK (start_at < end_at)');
            DB::statement("ALTER TABLE listing_change_requests ADD CONSTRAINT chk_listing_change_status CHECK (status IN ('PENDING_REVIEW','APPROVED','REJECTED','WITHDRAWN'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_media');
        Schema::dropIfExists('listing_change_requests');
        Schema::dropIfExists('availability_slots');
        Schema::dropIfExists('listings');
        Schema::dropIfExists('caregiver_profiles');
        Schema::dropIfExists('pets');
        Schema::dropIfExists('media_objects');
    }
};
