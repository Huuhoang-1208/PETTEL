<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('caregiver_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->restrictOnDelete();
            $table->string('listing_title');
            $table->string('sitter_name');
            $table->timestamps();
            $table->unique(['owner_id', 'listing_id']);
            $table->index(['caregiver_user_id', 'updated_at']);
        });
        Schema::create('inquiry_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained('service_inquiries')->restrictOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->json('context_json')->nullable();
            $table->uuid('request_key');
            $table->char('payload_hash', 64);
            $table->timestamp('created_at');
            $table->unique(['inquiry_id', 'sender_id', 'request_key'], 'inquiry_message_request_unique');
            $table->index(['inquiry_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_messages');
        Schema::dropIfExists('service_inquiries');
    }
};
