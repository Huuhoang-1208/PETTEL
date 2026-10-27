<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['ACTIVE', 'SUSPENDED', 'DELETION_PENDING', 'DEACTIVATED'])->default('ACTIVE');
            $table->timestamp('deletion_requested_at')->nullable();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('role', ['OWNER', 'CAREGIVER', 'ADMIN']);
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->primary(['user_id', 'role']);
            $table->index(['role', 'user_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id');
            $table->text('reason')->nullable();
            $table->string('request_id')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['resource_type', 'resource_id', 'created_at', 'id']);
            $table->index(['actor_user_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_roles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'deletion_requested_at']);
        });
    }
};
