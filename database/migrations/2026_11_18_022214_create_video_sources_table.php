<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('extension', 16);
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->string('path', 500);
            $table->string('disk', 32)->default('local');
            $table->decimal('duration_seconds', 12, 3)->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->boolean('has_video')->default(false);
            $table->boolean('has_audio')->default(false);
            $table->boolean('is_demo')->default(false);
            $table->string('status', 32)->default('uploaded')->index();
            $table->string('processing_stage', 64)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('rights_confirmed_at')->nullable();
            $table->timestamp('probed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_sources');
    }
};
