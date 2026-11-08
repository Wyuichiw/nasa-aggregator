<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nasa_media', function (Blueprint $table) {
            $table->id();
            $table->date('media_date')->unique();
            $table->string('title');
            $table->text('explanation');
            $table->enum('media_type', ['image', 'video']);
            $table->string('source_url', 2048);
            $table->string('local_storage_path')->nullable();
            $table->string('copyright')->nullable();
            $table->timestamps();

            $table->fullText(['title', 'explanation'], 'nasa_media_fulltext');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nasa_media');
    }
};
