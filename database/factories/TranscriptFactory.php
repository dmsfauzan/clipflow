<?php

namespace Database\Factories;

use App\Enums\TranscriptStatus;
use App\Models\Transcript;
use App\Models\VideoSource;
use Illuminate\Database\Eloquent\Factories\Factory;

class TranscriptFactory extends Factory
{
    protected $model = Transcript::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'video_source_id' => VideoSource::factory(),
            'language' => 'id',
            'model' => 'small',
            'full_text' => fake()->paragraphs(3, true),
            'segments' => [
                ['start' => 0.0, 'end' => 10.0, 'text' => 'Pertama, kita bahas dasar produktivitas.'],
                ['start' => 10.0, 'end' => 24.0, 'text' => 'Kedua, cara menyusun prioritas harian.'],
                ['start' => 24.0, 'end' => 44.0, 'text' => 'Ketiga, latihan kecil untuk fokus.'],
                ['start' => 44.0, 'end' => 70.0, 'text' => 'Keempat, jeda istirahat terstruktur.'],
                ['start' => 70.0, 'end' => 95.0, 'text' => 'Kelima, evaluasi mingguan sederhana.'],
            ],
            'status' => TranscriptStatus::Completed,
            'completed_at' => now(),
        ];
    }
}
