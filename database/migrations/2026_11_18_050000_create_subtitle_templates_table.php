<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subtitle_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('font', 100)->default('Arial');
            $table->unsignedSmallInteger('font_size')->default(30);
            $table->string('text_color', 9)->default('#FFFFFF');
            $table->string('outline_color', 9)->default('#000000');
            $table->unsignedTinyInteger('outline_width')->default(2);
            $table->unsignedTinyInteger('shadow')->default(0);
            $table->string('background_color', 9)->nullable();
            $table->unsignedTinyInteger('background_opacity')->default(0);
            $table->string('position', 16)->default('bottom');
            $table->unsignedSmallInteger('margin_x')->default(30);
            $table->unsignedSmallInteger('margin_y')->default(40);
            $table->unsignedTinyInteger('max_chars_per_line')->default(42);
            $table->unsignedTinyInteger('max_lines')->default(2);
            $table->boolean('bold')->default(false);
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subtitle_templates');
    }
};
