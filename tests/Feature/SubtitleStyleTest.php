<?php

namespace Tests\Feature;

use App\Models\Clip;
use App\Models\SubtitleTemplate;
use App\Models\VideoSource;
use App\Services\ProcessRunner;
use App\Services\VideoRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class SubtitleStyleTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    public function test_active_template_controls_style_and_margin_inside_video(): void
    {
        Storage::fake('local');

        SubtitleTemplate::create(array_merge(SubtitleTemplate::defaults(), [
            'name' => 'Kustom',
            'font_size' => 24,
            'margin_y' => 50,
            'outline_width' => 1,
        ]));

        [$clip, $source] = $this->renderableClip();

        $ass = $this->renderAndReadAss($clip, $source);

        $this->assertStringContainsString('PlayResX: 1080', $ass);
        $this->assertStringContainsString('Style: Default,Arial,24,', $ass);
        $this->assertStringContainsString('&H66000000', $ass);
        $this->assertStringContainsString(',1,1,0,2,30,30,737,1', $ass);
        $this->assertStringContainsString('Dialogue: 0,0:00:00.00,0:00:10.00', $ass);
    }

    public function test_top_position_uses_top_padding(): void
    {
        Storage::fake('local');

        SubtitleTemplate::create(array_merge(SubtitleTemplate::defaults(), [
            'name' => 'Atas',
            'position' => 'top',
            'margin_y' => 20,
        ]));

        [$clip, $source] = $this->renderableClip();

        $ass = $this->renderAndReadAss($clip, $source);

        $this->assertStringContainsString(',8,30,30,707,1', $ass);
    }

    public function test_background_box_uses_template_color_and_opacity(): void
    {
        Storage::fake('local');

        SubtitleTemplate::create(array_merge(SubtitleTemplate::defaults(), [
            'name' => 'Box',
            'background_color' => '#14532D',
            'background_opacity' => 50,
        ]));

        [$clip, $source] = $this->renderableClip();

        $ass = $this->renderAndReadAss($clip, $source);

        $this->assertStringContainsString('&H802D5314', $ass);
        $this->assertStringContainsString(',3,6,0,2,', $ass);
    }

    public function test_clip_and_source_template_overrides_are_respected(): void
    {
        Storage::fake('local');

        SubtitleTemplate::create(array_merge(SubtitleTemplate::defaults(), ['name' => 'Default 30', 'font_size' => 30]));
        $sourceTemplate = SubtitleTemplate::create(array_merge(SubtitleTemplate::defaults(), ['name' => 'Sumber 26', 'font_size' => 26, 'is_default' => false]));
        $clipTemplate = SubtitleTemplate::create(array_merge(SubtitleTemplate::defaults(), ['name' => 'Klip 22', 'font_size' => 22, 'is_default' => false]));

        [$clip, $source] = $this->renderableClip();
        $source->update(['subtitle_template_id' => $sourceTemplate->id]);
        $clip->update(['subtitle_template_id' => $clipTemplate->id]);

        $this->assertStringContainsString(',Arial,22,', $this->renderAndReadAss($clip->fresh(), $source->fresh()));

        $clip->update(['subtitle_template_id' => null]);

        $this->assertStringContainsString(',Arial,26,', $this->renderAndReadAss($clip->fresh(), $source->fresh()));
    }

    private function renderAndReadAss(Clip $clip, VideoSource $source): string
    {
        $this->instance(ProcessRunner::class, new class extends ProcessRunner
        {
            public function run(array $command, int $timeout = 120, ?string $workingDirectory = null): string
            {
                $output = (string) end($command);
                file_put_contents($output, str_repeat('v', 512));

                return '';
            }
        });

        $result = app(VideoRenderService::class)->render($clip, $source);

        Storage::disk('local')->assertExists($result['ass']);

        return Storage::disk('local')->get($result['ass']);
    }

    /**
     * @return array{0: Clip, 1: VideoSource}
     */
    private function renderableClip(): array
    {
        $source = $this->sourceFor($this->owner(), [
            'duration_seconds' => 120,
            'width' => 1920,
            'height' => 1080,
            'path' => 'video-sources/test.mp4',
        ]);
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
