<?php

namespace Tests\Feature;

use App\Enums\VideoSourceStatus;
use App\Jobs\SelectClipsForSource;
use App\Models\Transcript;
use App\Models\VideoSource;
use App\Services\Ai\AiChatClientFactory;
use App\Services\ClipSelectionService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class PipelineFailureTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    public function test_settings_defaults_survive_missing_config_keys(): void
    {
        config([
            'clipflow.publishing' => [],
            'clipflow.ai' => [],
            'clipflow.transcription' => [],
            'clipflow.retention' => [],
            'clipflow.media' => [],
        ]);

        $defaults = app(SettingsService::class)->defaults();

        $this->assertSame(30, $defaults['publishing.daily_limit']);
        $this->assertSame(10000, $defaults['publishing.quota_daily_units']);
        $this->assertSame(720, $defaults['pipeline.render_width']);
    }

    public function test_selection_failure_marks_source_failed_instead_of_stuck_selecting(): void
    {
        $source = $this->sourceFor($this->owner(), ['status' => 'selecting']);
        $source->transcript()->create([
            'language' => 'id',
            'model' => 'test',
            'full_text' => 'x',
            'segments' => [['start' => 0, 'end' => 30, 'text' => 'Kalimat uji.']],
            'status' => 'completed',
        ]);

        $this->instance(ClipSelectionService::class, new class(app(SettingsService::class), app(AiChatClientFactory::class)) extends ClipSelectionService
        {
            public function selectForSource(VideoSource $source, Transcript $transcript): array
            {
                throw new \RuntimeException('konfigurasi rusak');
            }
        });

        try {
            $this->app->call([new SelectClipsForSource($source->fresh()), 'handle']);
        } catch (\Throwable) {
            // Kegagalan dicatat pada sumber.
        }

        $source->refresh();

        $this->assertSame(VideoSourceStatus::Failed, $source->status);
        $this->assertStringContainsString('konfigurasi rusak', (string) $source->failure_message);
    }

    public function test_failed_handler_marks_source_failed_when_still_selecting(): void
    {
        $source = $this->sourceFor($this->owner(), ['status' => 'selecting']);

        (new SelectClipsForSource($source))->failed(new \RuntimeException('worker crash'));

        $this->assertSame(VideoSourceStatus::Failed, $source->fresh()->status);
    }
}
