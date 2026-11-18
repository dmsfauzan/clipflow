<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_sources', function (Blueprint $table): void {
            $table->foreignId('subtitle_template_id')->nullable()->after('user_id')
                ->constrained('subtitle_templates')->nullOnDelete();
        });

        Schema::table('clips', function (Blueprint $table): void {
            $table->foreignId('subtitle_template_id')->nullable()->after('video_source_id')
                ->constrained('subtitle_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('video_sources', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subtitle_template_id');
        });

        Schema::table('clips', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subtitle_template_id');
        });
    }
};
