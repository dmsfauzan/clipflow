<?php

namespace Tests\Feature;

use App\Jobs\RenderClip;
use App\Livewire\ReviewPanel;
use App\Livewire\SourceDetail;
use App\Models\Clip;
use App\Models\SubtitleTemplate;
use App\Services\SubtitleTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class SubtitleTemplateAssignmentTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    public function test_source_detail_can_save_default_template(): void
    {
        $user = $this->owner();
        $source = $this->sourceFor($user);
        $template = SubtitleTemplate::create(array_merge(SubtitleTemplate::defaults(), [
            'name' => 'Sumber Kustom',
            'is_default' => false,
        ]));

        Livewire::actingAs($user)
            ->test(SourceDetail::class, ['source' => $source])
            ->set('subtitleTemplateId', $template->id)
            ->call('saveTemplate')
            ->assertHasNoErrors();

        $this->assertSame($template->id, $source->fresh()->subtitle_template_id);
    }

    public function test_review_panel_applies_template_and_queues_rerender(): void
    {
        Queue::fake();

        $user = $this->owner();
        $source = $this->sourceFor($user);
        $source->transcript()->create([
            'language' => 'id',
            'model' => 'test',
            'full_text' => 'Transkrip uji',
            'segments' => [['start' => 0, 'end' => 30, 'text' => 'Kalimat uji.']],
            'status' => 'completed',
        ]);

        $clip = Clip::factory()->create([
            'video_source_id' => $source->id,
            'user_id' => $user->id,
            'transcript_id' => $source->transcript->id,
            'status' => 'review',
        ]);

        $template = SubtitleTemplate::create(array_merge(SubtitleTemplate::defaults(), [
            'name' => 'Klip Kustom',
            'is_default' => false,
        ]));

        Livewire::actingAs($user)
            ->test(ReviewPanel::class, ['clip' => $clip])
            ->set('subtitleTemplateId', $template->id)
            ->call('applyTemplate')
            ->assertHasNoErrors();

        $fresh = $clip->fresh();

        $this->assertSame($template->id, $fresh->subtitle_template_id);
        $this->assertSame('rendering', $fresh->status->value);
        Queue::assertPushed(RenderClip::class);
    }

    public function test_approved_clip_rejects_template_change(): void
    {
        Queue::fake();

        $user = $this->owner();
        $source = $this->sourceFor($user);
        $source->transcript()->create([
            'language' => 'id',
            'model' => 'test',
            'full_text' => 'Transkrip uji',
            'segments' => [['start' => 0, 'end' => 30, 'text' => 'Kalimat uji.']],
            'status' => 'completed',
        ]);

        $clip = Clip::factory()->create([
            'video_source_id' => $source->id,
            'user_id' => $user->id,
            'transcript_id' => $source->transcript->id,
            'status' => 'approved',
        ]);

        $template = SubtitleTemplate::create(array_merge(SubtitleTemplate::defaults(), [
            'name' => 'Tidak Boleh',
            'is_default' => false,
        ]));

        Livewire::actingAs($user)
            ->test(ReviewPanel::class, ['clip' => $clip])
            ->set('subtitleTemplateId', $template->id)
            ->call('applyTemplate')
            ->assertHasErrors(['subtitleTemplate']);

        $this->assertNull($clip->fresh()->subtitle_template_id);
    }

    public function test_active_template_is_created_when_none_exists(): void
    {
        $template = app(SubtitleTemplateService::class)->active();

        $this->assertTrue($template->is_default);
        $this->assertSame('Default Clipflow', $template->name);
    }
}
