<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_completion_followups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->dateTime('reminder_sent_at', 6)->nullable();
            $table->dateTime('admin_queued_at', 6)->nullable();
            $table->dateTime('resolved_at', 6)->nullable();
            $table->timestamps(6);

            $table->index(['admin_queued_at', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_completion_followups');
    }
};
