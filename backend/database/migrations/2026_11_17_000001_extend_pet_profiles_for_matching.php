<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->string('species_detail', 100)->nullable();
            $table->text('food_allergies')->nullable();
            $table->text('foods_to_avoid')->nullable();
            $table->json('behavior_tags')->nullable();
            $table->json('care_support_tags')->nullable();
            $table->json('vaccination_statuses')->nullable();
            $table->date('core_vaccines_date')->nullable();
            $table->date('rabies_vaccine_date')->nullable();
            $table->date('deworming_date')->nullable();
            $table->foreignId('vaccination_record_media_id')->nullable()
                ->constrained('media_objects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vaccination_record_media_id');
            $table->dropColumn([
                'species_detail',
                'food_allergies',
                'foods_to_avoid',
                'behavior_tags',
                'care_support_tags',
                'vaccination_statuses',
                'core_vaccines_date',
                'rabies_vaccine_date',
                'deworming_date',
            ]);
        });
    }
};
