<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_sources', function (Blueprint $table): void {
            $table->string('source_type', 16)->default('upload');
            $table->text('source_url')->nullable();
            $table->string('source_url_hash', 64)->nullable();
            $table->unsignedTinyInteger('import_progress')->nullable();
            $table->timestamp('import_started_at')->nullable();
            $table->timestamp('import_completed_at')->nullable();
            $table->unsignedBigInteger('downloaded_bytes')->nullable();

            $table->index(['user_id', 'source_url_hash']);
            $table->index(['status', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::table('video_sources', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'source_url_hash']);
            $table->dropIndex(['status', 'source_type']);
            $table->dropColumn([
                'source_type',
                'source_url',
                'source_url_hash',
                'import_progress',
                'import_started_at',
                'import_completed_at',
                'downloaded_bytes',
            ]);
        });
    }
};
