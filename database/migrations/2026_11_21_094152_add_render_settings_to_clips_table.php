<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clips', function (Blueprint $table): void {
            $table->unsignedSmallInteger('foreground_width')->default(90)->after('duration_seconds');
            $table->unsignedSmallInteger('blur_sigma')->default(18)->after('foreground_width');
            $table->boolean('subtitles_enabled')->default(true)->after('blur_sigma');
        });
    }

    public function down(): void
    {
        Schema::table('clips', function (Blueprint $table): void {
            $table->dropColumn(['foreground_width', 'blur_sigma', 'subtitles_enabled']);
        });
    }
};
