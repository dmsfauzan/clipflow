<?php

namespace Tests\Feature;

use App\Enums\ClipStatus;
use App\Models\Clip;
use App\Models\VideoSource;
use App\Services\MediaProbeService;
use App\Services\ProcessRunner;
use App\Services\VideoRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class RenderCompositionTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    private ProcessRunner $processes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processes = app(ProcessRunner::class);

        if (! $this->processes->isAvailable(config('clipflow.media.ffmpeg_binary'))
            || ! $this->processes->isAvailable(config('clipflow.media.ffprobe_binary'))) {
            $this->markTestSkipped('FFmpeg/FFprobe tidak tersedia untuk uji render nyata.');
        }

        Storage::fake('local');
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: bool}>
     */
    public static function sourceShapes(): array
    {
        return [
            'horizontal' => [1280, 720, true],
            'vertical' => [720, 1280, true],
            'square' => [720, 720, true],
            'tanpa audio' => [1280, 720, false],
        ];
    }

    #[DataProvider('sourceShapes')]
    public function test_render_produces_vertical_blur_composition(int $width, int $height, bool $withAudio): void
    {
        $source = $this->makeSource($width, $height, $withAudio);
        $clip = $this->makeClip($source);

        $result = app(VideoRenderService::class)->render($clip, $source);

        Storage::disk('local')->assertExists($result['video']);

        $probe = $this->probeOutput(Storage::disk('local')->path($result['video']));

        $this->assertSame(1080, $probe['width']);
        $this->assertSame(1920, $probe['height']);
        $this->assertSame('h264', $probe['codec']);
        $this->assertSame('yuv420p', $probe['pix_fmt']);
        $this->assertSame('30/1', $probe['fps']);
        $this->assertSame('1:1', $probe['sar']);
        $this->assertEqualsWithDelta(2.0, $probe['duration'], 0.35);
        $this->assertSame($withAudio ? 1 : 0, $probe['audio_streams']);

        [$foregroundVariance, $backgroundVariance] = $this->variances($result['video']);

        $this->assertGreaterThan(0.0, $foregroundVariance);
        $this->assertGreaterThan($backgroundVariance * 1.5, $foregroundVariance, 'Foreground harus lebih tajam daripada background blur.');
    }

    public function test_blur_and_foreground_settings_change_the_export(): void
    {
        $source = $this->makeSource(1280, 720, true);
        $renderer = app(VideoRenderService::class);

        $default = $renderer->render($this->makeClip($source, ['blur_sigma' => 18, 'foreground_width' => 90]), $source);
        $noBlur = $renderer->render($this->makeClip($source, ['blur_sigma' => 0, 'foreground_width' => 90]), $source);
        $smallForeground = $renderer->render($this->makeClip($source, ['blur_sigma' => 18, 'foreground_width' => 60]), $source);

        $this->assertNotSame($default['media_hash'], $noBlur['media_hash']);
        $this->assertNotSame($default['media_hash'], $smallForeground['media_hash']);
    }

    public function test_subtitles_are_burned_after_composition(): void
    {
        $source = $this->makeSource(1280, 720, true);
        $source->transcript()->create([
            'language' => 'id',
            'model' => 'test',
            'full_text' => 'Kalimat uji dengan karakter khusus: <>&{} 100%.',
            'segments' => [
                ['start' => 0, 'end' => 1.5, 'text' => 'Kalimat uji dengan karakter khusus: <>&{} 100%.'],
            ],
            'status' => 'completed',
        ]);

        $clip = $this->makeClip($source, [
            'transcript_id' => $source->transcript->id,
            'subtitles_enabled' => true,
        ]);

        $result = app(VideoRenderService::class)->render($clip->fresh(), $source->fresh());

        Storage::disk('local')->assertExists($result['video']);
        Storage::disk('local')->assertExists($result['ass']);
        Storage::disk('local')->assertExists($result['subtitle']);

        $probe = $this->probeOutput(Storage::disk('local')->path($result['video']));
        $this->assertSame(1080, $probe['width']);
        $this->assertSame(1920, $probe['height']);
    }

    private function makeSource(int $width, int $height, bool $withAudio): VideoSource
    {
        $tmp = sys_get_temp_dir().'\\clipflow-src-'.uniqid().'.mp4';
        $args = [
            config('clipflow.media.ffmpeg_binary'),
            '-hide_banner', '-loglevel', 'error', '-y',
            '-f', 'lavfi', '-i', sprintf('testsrc2=size=%dx%d:rate=30:duration=2', $width, $height),
        ];

        if ($withAudio) {
            array_push($args, '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2');
        }

        array_push($args, '-vf', 'noise=alls=60:allf=t');

        if ($withAudio) {
            array_push($args, '-shortest', '-c:a', 'aac');
        } else {
            $args[] = '-an';
        }

        array_push($args, '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-t', '2', $tmp);

        $this->processes->run($args, 120);

        $path = 'video-sources/'.uniqid('', true).'.mp4';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        $absolute = Storage::disk('local')->path($path);
        $info = app(MediaProbeService::class)->probe($absolute);

        return $this->sourceFor($this->owner(), [
            'path' => $path,
            'duration_seconds' => $info['duration_seconds'],
            'width' => $info['width'],
            'height' => $info['height'],
            'has_video' => true,
            'has_audio' => $info['has_audio'],
            'status' => 'ready',
            'probed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeClip(VideoSource $source, array $attributes = []): Clip
    {
        return Clip::factory()->create(array_merge([
            'video_source_id' => $source->id,
            'user_id' => $source->user_id,
            'transcript_id' => null,
            'start_seconds' => 0,
            'end_seconds' => 2,
            'duration_seconds' => 2,
            'status' => ClipStatus::Rendering,
            'subtitles_enabled' => true,
        ], $attributes));
    }

    /**
     * @return array{width: int, height: int, codec: string, pix_fmt: string, fps: string, sar: string, duration: float, audio_streams: int}
     */
    private function probeOutput(string $path): array
    {
        $output = $this->processes->run([
            config('clipflow.media.ffprobe_binary'),
            '-v', 'error',
            '-print_format', 'json',
            '-show_streams',
            '-show_format',
            $path,
        ], 60);

        $data = json_decode($output, true);
        $video = collect($data['streams'])->firstWhere('codec_type', 'video');
        $audioCount = collect($data['streams'])->where('codec_type', 'audio')->count();

        return [
            'width' => (int) $video['width'],
            'height' => (int) $video['height'],
            'codec' => (string) $video['codec_name'],
            'pix_fmt' => (string) $video['pix_fmt'],
            'fps' => (string) $video['r_frame_rate'],
            'sar' => (string) ($video['sample_aspect_ratio'] ?? ''),
            'duration' => (float) $data['format']['duration'],
            'audio_streams' => $audioCount,
        ];
    }

    /**
     * Bandingkan variansi luminansi area tengah (foreground) dengan sudut (background blur).
     *
     * @return array{0: float, 1: float}
     */
    private function variances(string $relative): array
    {
        $raw = tempnam(sys_get_temp_dir(), 'clipflow-frame-');

        $this->processes->run([
            config('clipflow.media.ffmpeg_binary'),
            '-hide_banner', '-loglevel', 'error', '-y',
            '-ss', '0.5',
            '-i', Storage::disk('local')->path($relative),
            '-frames:v', '1',
            '-f', 'rawvideo',
            '-pix_fmt', 'rgb24',
            $raw,
        ], 60);

        $bytes = file_get_contents($raw);
        @unlink($raw);

        $width = 1080;
        $height = 1920;

        return [
            $this->regionVariance($bytes, $width, $height, 340, 760, 400, 400),
            $this->regionVariance($bytes, $width, $height, 0, 0, 140, 140),
        ];
    }

    private function regionVariance(string $bytes, int $frameWidth, int $frameHeight, int $x0, int $y0, int $w, int $h): float
    {
        $values = [];
        $maxX = min($x0 + $w, $frameWidth);
        $maxY = min($y0 + $h, $frameHeight);

        for ($y = $y0; $y < $maxY; $y += 2) {
            for ($x = $x0; $x < $maxX; $x += 2) {
                $offset = ($y * $frameWidth + $x) * 3;
                $values[] = 0.299 * ord($bytes[$offset]) + 0.587 * ord($bytes[$offset + 1]) + 0.114 * ord($bytes[$offset + 2]);
            }
        }

        $count = count($values);

        if ($count === 0) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $sum = 0.0;

        foreach ($values as $value) {
            $sum += ($value - $mean) ** 2;
        }

        return $sum / $count;
    }
}
