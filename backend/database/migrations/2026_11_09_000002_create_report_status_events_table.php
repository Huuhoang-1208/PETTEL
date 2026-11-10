<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->text('reason')->nullable();
            $table->string('request_id', 36)->nullable();
            $table->dateTime('created_at', 6);

            $table->index(['report_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_status_events');
    }
};
