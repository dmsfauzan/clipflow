<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transcript_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('start_seconds', 10, 3);
            $table->decimal('end_seconds', 10, 3);
            $table->decimal('duration_seconds', 10, 3);
            $table->string('title', 255);
            $table->text('caption')->nullable();
            $table->text('reason')->nullable();
            $table->decimal('ai_score', 5, 2)->nullable();
            $table->string('status', 32)->default('rendering')->index();
            $table->text('failure_message')->nullable();
            $table->string('rendered_path', 500)->nullable();
            $table->string('thumbnail_path', 500)->nullable();
            $table->string('srt_path', 500)->nullable();
            $table->unsignedBigInteger('rendered_size_bytes')->nullable();
            $table->unsignedSmallInteger('rendered_width')->nullable();
            $table->unsignedSmallInteger('rendered_height')->nullable();
            $table->string('media_hash', 64)->nullable();
            $table->timestamp('render_started_at')->nullable();
            $table->timestamp('rendered_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['video_source_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clips');
    }
};
