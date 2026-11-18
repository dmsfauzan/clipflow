<?php

namespace Database\Factories;

use App\Enums\VideoSourceStatus;
use App\Models\User;
use App\Models\VideoSource;
use Illuminate\Database\Eloquent\Factories\Factory;

class VideoSourceFactory extends Factory
{
    protected $model = VideoSource::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_name' => fake()->words(3, true).'.mp4',
            'extension' => 'mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => fake()->numberBetween(10_000_000, 500_000_000),
            'path' => 'video-sources/'.fake()->uuid().'.mp4',
            'disk' => 'local',
            'duration_seconds' => 1200.0,
            'width' => 1920,
            'height' => 1080,
            'has_video' => true,
            'has_audio' => true,
            'is_demo' => false,
            'status' => VideoSourceStatus::Ready,
            'processing_stage' => null,
            'rights_confirmed_at' => now(),
            'probed_at' => now(),
            'processed_at' => now(),
        ];
    }
}
