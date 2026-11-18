<?php

namespace Tests\Feature;

use App\Enums\ClipStatus;
use App\Exceptions\AuthorizationException;
use App\Jobs\RenderClip;
use App\Models\Clip;
use App\Services\ClipRerenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class ClipRerenderTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    public function test_review_clip_can_be_queued_for_rerender(): void
    {
        Queue::fake();

        [$clip] = $this->clipWithTranscript(ClipStatus::Review);

        app(ClipRerenderService::class)->queue($clip);

        $this->assertSame(ClipStatus::Rendering, $clip->fresh()->status);
        Queue::assertPushed(RenderClip::class);
    }

    public function test_approved_clip_cannot_be_rerendered(): void
    {
        Queue::fake();

        [$clip] = $this->clipWithTranscript(ClipStatus::Approved);

        $this->expectException(AuthorizationException::class);

        app(ClipRerenderService::class)->queue($clip);
    }

    public function test_command_can_rerender_all_review_clips(): void
    {
        Queue::fake();

        [$first] = $this->clipWithTranscript(ClipStatus::Review);
        [$second] = $this->clipWithTranscript(ClipStatus::Review);

        $this->artisan('clipflow:rerender-subtitles', ['--limit' => 10])->assertSuccessful();

        $this->assertSame(ClipStatus::Rendering, $first->fresh()->status);
        $this->assertSame(ClipStatus::Rendering, $second->fresh()->status);
        Queue::assertPushed(RenderClip::class, 2);
    }

    /**
     * @return array{0: Clip}
     */
    private function clipWithTranscript(ClipStatus $status): array
    {
        $source = $this->sourceFor($this->owner());
        $source->transcript()->create([
            'language' => 'id',
            'model' => 'test',
            'full_text' => 'Transkrip uji',
            'segments' => [['start' => 0, 'end' => 30, 'text' => 'Kalimat uji.']],
            'status' => 'completed',
        ]);

        $clip = Clip::factory()->create([
            'video_source_id' => $source->id,
            'user_id' => $source->user_id,
            'transcript_id' => $source->transcript->id,
            'status' => $status,
        ]);

        return [$clip];
    }
}
