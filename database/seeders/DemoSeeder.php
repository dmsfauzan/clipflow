<?php

namespace Database\Seeders;

use App\Enums\ClipStatus;
use App\Enums\TranscriptStatus;
use App\Enums\VideoSourceStatus;
use App\Models\Clip;
use App\Models\Transcript;
use App\Models\User;
use App\Models\VideoSource;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->orderBy('id')->first();

        if (! $owner) {
            $this->command?->warn('Buat pemilik terlebih dahulu dengan `php artisan clipflow:create-owner`.');

            return;
        }

        $source = VideoSource::create([
            'user_id' => $owner->id,
            'original_name' => 'Demo — Podcast Produktivitas.mp4',
            'extension' => 'mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 420_000_000,
            'path' => 'demo/podcast-produktivitas.mp4',
            'disk' => 'local',
            'duration_seconds' => 1840,
            'width' => 1920,
            'height' => 1080,
            'has_video' => true,
            'has_audio' => true,
            'is_demo' => true,
            'status' => VideoSourceStatus::Ready,
            'processing_stage' => 'Data demo — file video tidak disertakan',
            'rights_confirmed_at' => now(),
            'probed_at' => now(),
            'processed_at' => now(),
        ]);

        Transcript::create([
            'video_source_id' => $source->id,
            'language' => 'id',
            'model' => 'demo',
            'full_text' => 'Ini adalah transkrip demo. ...',
            'segments' => [
                ['start' => 0.0, 'end' => 12.0, 'text' => 'Ini transkrip demo untuk menampilkan antarmuka.'],
                ['start' => 12.0, 'end' => 32.0, 'text' => 'File media asli tidak disertakan pada data demo.'],
                ['start' => 32.0, 'end' => 58.0, 'text' => 'Gunakan data ini hanya untuk melihat tata letak dashboard.'],
            ],
            'status' => TranscriptStatus::Completed,
            'completed_at' => now(),
        ]);

        Clip::create([
            'video_source_id' => $source->id,
            'user_id' => $owner->id,
            'transcript_id' => $source->transcript->id,
            'start_seconds' => 12,
            'end_seconds' => 42,
            'duration_seconds' => 30,
            'title' => 'Demo — Cara menjaga fokus saat bekerja',
            'caption' => 'Contoh caption demo. Tidak ada video sungguhan yang dirender.',
            'reason' => 'Data demo untuk menguji tampilan review.',
            'ai_score' => 72,
            'status' => ClipStatus::Review,
            'rendered_at' => now(),
        ]);

        Clip::create([
            'video_source_id' => $source->id,
            'user_id' => $owner->id,
            'transcript_id' => $source->transcript->id,
            'start_seconds' => 42,
            'end_seconds' => 78,
            'duration_seconds' => 36,
            'title' => 'Demo — Kebiasaan pagi yang sederhana',
            'caption' => 'Contoh caption demo kedua.',
            'reason' => 'Data demo untuk menguji daftar review.',
            'ai_score' => 65,
            'status' => ClipStatus::Review,
            'rendered_at' => now(),
        ]);

        $this->command?->warn('Data demo dibuat. Statistik demo tidak dicampur dengan statistik nyata.');
    }
}
