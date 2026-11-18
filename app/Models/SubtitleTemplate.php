<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubtitleTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'font', 'font_size', 'text_color', 'outline_color', 'outline_width', 'shadow',
        'background_color', 'background_opacity', 'position', 'margin_x', 'margin_y',
        'max_chars_per_line', 'max_lines', 'bold', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'font_size' => 'integer',
            'outline_width' => 'integer',
            'shadow' => 'integer',
            'background_opacity' => 'integer',
            'margin_x' => 'integer',
            'margin_y' => 'integer',
            'max_chars_per_line' => 'integer',
            'max_lines' => 'integer',
            'bold' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'name' => 'Default Clipflow',
            'font' => 'Arial',
            'font_size' => 30,
            'text_color' => '#FFFFFF',
            'outline_color' => '#000000',
            'outline_width' => 2,
            'shadow' => 0,
            'background_color' => null,
            'background_opacity' => 0,
            'position' => 'bottom',
            'margin_x' => 30,
            'margin_y' => 40,
            'max_chars_per_line' => 42,
            'max_lines' => 2,
            'bold' => false,
            'is_default' => true,
        ];
    }

    public function alignment(): int
    {
        return match ($this->position) {
            'top' => 8,
            'center' => 5,
            default => 2,
        };
    }
}
