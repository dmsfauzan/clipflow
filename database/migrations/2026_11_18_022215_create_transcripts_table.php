<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcripts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_source_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('language', 16)->default('id');
            $table->string('model', 64);
            $table->longText('full_text');
            $table->json('segments');
            $table->string('status', 32)->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcripts');
    }
};
