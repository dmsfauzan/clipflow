<?php

namespace Tests\Feature;

use App\Exceptions\ProcessException;
use App\Models\Clip;
use App\Models\VideoSource;
use App\Services\ProcessRunner;
use App\Services\VideoRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class VideoRenderServiceTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    public function test_render_creates_output_directory_and_supporting_files(): void
    {
        Storage::fake('local');

        [$clip, $source] = $this->renderableClip();

        $this->instance(ProcessRunner::class, new class extends ProcessRunner
        {
            public function run(array $command, int $timeout = 120, ?string $workingDirectory = null): string
            {
                $output = (string) end($command);
                $directory = dirname($output);

                if (! is_dir($directory)) {
                    throw new ProcessException('Direktori output belum dibuat: '.$directory);
                }

                file_put_contents($output, str_repeat('v', 1024));

                return '';
            }
        });

        $result = app(VideoRenderService::class)->render($clip, $source);

        Storage::disk('local')->assertExists($result['video']);
        Storage::disk('local')->assertExists($result['subtitle']);
        Storage::disk('local')->assertExists($result['ass']);
        Storage::disk('local')->assertExists($result['thumbnail']);

        $this->assertSame(1024, $result['size_bytes']);
        $this->assertSame(1080, $result['width']);
        $this->assertSame(1920, $result['height']);
        $this->assertSame(64, strlen($result['media_hash']));
        $this->assertStringContainsString('00:00:00,000', Storage::disk('local')->get($result['subtitle']));
    }

    public function test_thumbnail_failure_does_not_fail_the_render(): void
    {
        Storage::fake('local');

        [$clip, $source] = $this->renderableClip();

        $this->instance(ProcessRunner::class, new class extends ProcessRunner
        {
            public function run(array $command, int $timeout = 120, ?string $workingDirectory = null): string
            {
                $output = (string) end($command);

                if (str_ends_with($output, '.jpg')) {
                    throw new ProcessException('Thumbnail gagal');
                }

                file_put_contents($output, str_repeat('v', 512));

                return '';
            }
        });

        $result = app(VideoRenderService::class)->render($clip, $source);

        $this->assertNull($result['thumbnail']);
        Storage::disk('local')->assertExists($result['video']);
    }

    /**
     * @return array{0: Clip, 1: VideoSource}
     */
    private function renderableClip(): array
    {
        $source = $this->sourceFor($this->owner(), ['duration_seconds' => 120, 'path' => 'video-sources/test.mp4']);

        $source->transcript()->create([
            'language' => 'id',
            'model' => 'test',
            'full_text' => 'Transkrip uji',
            'segments' => [
                ['start' => 10, 'end' => 20, 'text' => 'Segmen pertama.'],
                ['start' => 20, 'end' => 40, 'text' => 'Segmen kedua.'],
            ],
            'status' => 'completed',
        ]);

        $clip = Clip::factory()->create([
            'video_source_id' => $source->id,
            'user_id' => $source->user_id,
            'transcript_id' => $source->transcript->id,
            'start_seconds' => 10,
            'end_seconds' => 40,
            'duration_seconds' => 30,
            'status' => 'rendering',
        ]);

        Storage::disk('local')->put($source->path, 'source-video');

        return [$clip, $source->fresh()];
    }
}
