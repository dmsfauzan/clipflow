<?php

namespace Tests\Feature;

use App\Enums\ClipStatus;
use App\Enums\VideoSourceStatus;
use App\Jobs\ProbeVideoSource;
use App\Jobs\RenderClip;
use App\Models\Clip;
use App\Services\ClipRerenderService;
use App\Services\MediaProbeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class ManualClipTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    public function test_owner_can_create_manual_clip_and_queue_render(): void
    {
        Queue::fake();

        $user = $this->owner();
        $source = $this->sourceFor($user, [
            'duration_seconds' => 120,
            'probed_at' => now(),
            'status' => 'ready',
        ]);

        Livewire::actingAs($user)
            ->test('source-detail', ['source' => $source])
            ->set('manualStart', 10)
            ->set('manualEnd', 40)
            ->set('manualTitle', 'Momen manual')
            ->call('createManualClip')
            ->assertHasNoErrors();

        $clip = Clip::query()->where('video_source_id', $source->id)->sole();

        $this->assertSame('Momen manual', $clip->title);
        $this->assertSame(ClipStatus::Rendering, $clip->status);
        $this->assertSame($user->id, $clip->user_id);
        $this->assertEqualsWithDelta(30.0, (float) $clip->duration_seconds, 0.001);
        $this->assertSame(90, $clip->foreground_width);
        $this->assertSame(18, $clip->blur_sigma);

        Queue::assertPushed(RenderClip::class);
    }

    public function test_timestamp_beyond_source_duration_is_rejected(): void
    {
        Queue::fake();

        $user = $this->owner();
        $source = $this->sourceFor($user, [
            'duration_seconds' => 60,
            'probed_at' => now(),
            'status' => 'ready',
        ]);

        Livewire::actingAs($user)
            ->test('source-detail', ['source' => $source])
            ->set('manualStart', 30)
            ->set('manualEnd', 90)
            ->call('createManualClip')
            ->assertHasErrors(['manualEnd']);

        $this->assertDatabaseCount('clips', 0);
        Queue::assertNothingPushed();
    }

    public function test_end_before_start_is_rejected(): void
    {
        Queue::fake();

        $user = $this->owner();
        $source = $this->sourceFor($user, [
            'duration_seconds' => 60,
            'probed_at' => now(),
            'status' => 'ready',
        ]);

        Livewire::actingAs($user)
            ->test('source-detail', ['source' => $source])
            ->set('manualStart', 40)
            ->set('manualEnd', 20)
            ->call('createManualClip')
            ->assertHasErrors(['manualEnd']);

        $this->assertDatabaseCount('clips', 0);
    }

    public function test_overlapping_manual_clip_is_rejected(): void
    {
        Queue::fake();

        $user = $this->owner();
        $source = $this->sourceFor($user, [
            'duration_seconds' => 120,
            'probed_at' => now(),
            'status' => 'ready',
        ]);

        Clip::factory()->create([
            'video_source_id' => $source->id,
            'user_id' => $user->id,
            'start_seconds' => 10,
            'end_seconds' => 50,
            'duration_seconds' => 40,
            'status' => ClipStatus::Review,
        ]);

        Livewire::actingAs($user)
            ->test('source-detail', ['source' => $source])
            ->set('manualStart', 30)
            ->set('manualEnd', 70)
            ->call('createManualClip')
            ->assertHasErrors(['manualStart']);

        $this->assertSame(1, $source->clips()->count());
    }

    public function test_another_user_cannot_open_source_detail(): void
    {
        $owner = $this->owner();
        $intruder = $this->owner();

        $source = $this->sourceFor($owner, [
            'duration_seconds' => 60,
            'probed_at' => now(),
            'status' => 'ready',
        ]);

        Livewire::actingAs($intruder)
            ->test('source-detail', ['source' => $source])
            ->assertForbidden();
    }

    public function test_probe_accepts_source_without_audio(): void
    {
        $source = $this->sourceFor($this->owner(), ['duration_seconds' => 30]);

        $probe = new class extends MediaProbeService
        {
            public function __construct() {}

            public function probe(string $absolutePath): array
            {
                return [
                    'duration_seconds' => 30.0,
                    'width' => 1080,
                    'height' => 1920,
                    'has_video' => true,
                    'has_audio' => false,
                    'video_codec' => 'h264',
                    'audio_codec' => null,
                    'container' => 'mov,mp4',
                ];
            }
        };

        $source->update([
            'path' => 'video-sources/silent.mp4',
            'status' => VideoSourceStatus::Uploaded,
            'probed_at' => null,
            'has_audio' => true,
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('video-sources/silent.mp4', 'silent');

        $this->app->instance(MediaProbeService::class, $probe);

        $job = new ProbeVideoSource($source->fresh());
        $this->app->call([$job, 'handle']);

        $source->refresh();

        $this->assertFalse($source->has_audio);
        $this->assertSame(VideoSourceStatus::Ready, $source->status);
    }

    public function test_rerender_settings_revoke_approval_and_schedule(): void
    {
        Queue::fake();

        $user = $this->owner();
        [$clip, $channel, $approval] = $this->approvedClipFor($user);

        $this->publicationJobFor($clip, $approval, $channel);

        app(ClipRerenderService::class)->updateSettings($clip, $user, [
            'start_seconds' => 0,
            'end_seconds' => 25,
            'duration_seconds' => 25,
            'foreground_width' => 80,
            'blur_sigma' => 30,
            'subtitles_enabled' => false,
        ]);

        $clip->refresh();

        $this->assertSame(ClipStatus::Rendering, $clip->status);
        $this->assertSame(80, $clip->foreground_width);
        $this->assertSame(30, $clip->blur_sigma);
        $this->assertFalse($clip->subtitles_enabled);
        $this->assertNull($clip->rendered_path);
        $this->assertFalse((bool) $approval->fresh()->is_current);
        $this->assertSame('cancelled', $clip->publicationJob->fresh()->status->value);

        Queue::assertPushed(RenderClip::class);
    }

    public function test_foreground_and_blur_are_clamped_to_configured_bounds(): void
    {
        config([
            'clipflow.media.render.foreground_width_percent.min' => 50,
            'clipflow.media.render.foreground_width_percent.max' => 100,
            'clipflow.media.render.blur_sigma.min' => 0,
            'clipflow.media.render.blur_sigma.max' => 60,
        ]);

        $clip = Clip::factory()->make(['foreground_width' => 500, 'blur_sigma' => 500]);

        $this->assertSame(100, min(100, max(50, $clip->foreground_width)));
        $this->assertSame(60, min(60, max(0, $clip->blur_sigma)));
    }
}
