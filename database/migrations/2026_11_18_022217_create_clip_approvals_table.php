<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clip_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('publishing_channel_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('approval_version')->default(1);
            $table->string('content_hash', 64);
            $table->string('privacy', 32);
            $table->boolean('made_for_kids')->default(false);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('approved_at');
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoke_reason')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->unique(['clip_id', 'approval_version']);
            $table->index(['clip_id', 'revoked_at']);
            $table->index(['user_id', 'approved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clip_approvals');
    }
};
