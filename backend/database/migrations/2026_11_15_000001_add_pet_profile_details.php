<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->string('breed', 100)->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->string('microchip_number', 80)->nullable();
            $table->boolean('is_neutered')->default(false);
            $table->json('health_flags')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn(['breed', 'weight_kg', 'microchip_number', 'is_neutered', 'health_flags']);
        });
    }
};
