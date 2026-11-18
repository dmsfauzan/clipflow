<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clips', function (Blueprint $table): void {
            $table->string('ass_path', 500)->nullable()->after('srt_path');
        });
    }

    public function down(): void
    {
        Schema::table('clips', function (Blueprint $table): void {
            $table->dropColumn('ass_path');
        });
    }
};
