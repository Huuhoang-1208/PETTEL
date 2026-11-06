<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
        });

        Schema::create('message_attachments', function (Blueprint $table) {
            $table->foreignId('message_id')->constrained()->restrictOnDelete();
            $table->foreignId('media_object_id')->unique()->constrained()->restrictOnDelete();
            $table->primary(['message_id', 'media_object_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');

        DB::table('messages')->whereNull('body')->update(['body' => '']);
        Schema::table('messages', function (Blueprint $table) {
            $table->text('body')->nullable(false)->change();
        });
    }
};
