<?php

namespace Database\Factories;

use App\Enums\ClipStatus;
use App\Models\Clip;
use App\Models\Transcript;
use App\Models\User;
use App\Models\VideoSource;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClipFactory extends Factory
{
    protected $model = Clip::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->numberBetween(0, 900);
        $duration = fake()->numberBetween(15, 60);

        return [
            'video_source_id' => VideoSource::factory(),
            'user_id' => User::factory(),
            'transcript_id' => null,
            'start_seconds' => $start,
            'end_seconds' => $start + $duration,
            'duration_seconds' => $duration,
            'title' => fake()->sentence(4),
            'caption' => fake()->sentence(8),
            'reason' => fake()->sentence(6),
            'ai_score' => fake()->randomFloat(2, 40, 90),
            'status' => ClipStatus::Review,
            'rendered_path' => 'clips/'.fake()->uuid().'/video.mp4',
            'thumbnail_path' => 'clips/'.fake()->uuid().'/thumbnail.jpg',
            'srt_path' => 'clips/'.fake()->uuid().'/subtitles.srt',
            'rendered_size_bytes' => 12_000_000,
            'rendered_width' => 720,
            'rendered_height' => 1280,
            'media_hash' => hash('sha256', 'test-media'),
            'render_started_at' => now(),
            'rendered_at' => now(),
        ];
    }

    /**
     * Klip yang belum selesai dirender.
     */
    public function pendingRender(): static
    {
        return $this->state(fn (): array => [
            'status' => ClipStatus::Rendering,
            'rendered_path' => null,
            'thumbnail_path' => null,
            'srt_path' => null,
            'rendered_size_bytes' => null,
            'media_hash' => null,
            'render_started_at' => null,
            'rendered_at' => null,
        ]);
    }

    public function forSource(VideoSource $source, ?Transcript $transcript = null): static
    {
        return $this->state(fn (): array => [
            'video_source_id' => $source->id,
            'user_id' => $source->user_id,
            'transcript_id' => $transcript?->id ?? $source->transcript?->id,
        ]);
    }
}
