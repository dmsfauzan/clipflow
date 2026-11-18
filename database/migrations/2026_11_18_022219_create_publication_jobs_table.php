<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clip_approval_id')->constrained()->cascadeOnDelete();
            $table->foreignId('publishing_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('scheduled')->index();
            $table->timestamp('scheduled_for')->index();
            $table->date('slot_date')->index();
            $table->string('slot_key', 64)->nullable()->unique();
            $table->string('privacy', 32);
            $table->boolean('made_for_kids')->default(false);
            $table->string('content_hash', 64)->index();
            $table->string('title', 255);
            $table->text('caption')->nullable();
            $table->string('youtube_video_id', 64)->nullable();
            $table->text('resumable_session_uri')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('error_category', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
            $table->unique(['clip_approval_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_jobs');
    }
};
