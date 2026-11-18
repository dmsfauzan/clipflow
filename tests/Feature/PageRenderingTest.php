<?php

namespace Tests\Feature;

use App\Livewire\ChannelConnect;
use App\Livewire\Dashboard;
use App\Livewire\ReviewPanel;
use App\Livewire\ScheduleBoard;
use App\Livewire\SettingsForm;
use App\Livewire\SourceList;
use App\Livewire\SourceUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class PageRenderingTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    public function test_all_main_pages_render_for_the_owner(): void
    {
        $user = $this->owner();
        $channel = $this->channelFor($user);
        [$clip, $channel, $approval] = $this->approvedClipFor($user, $channel);
        $this->publicationJobFor($clip, $approval, $channel, now()->addHour());

        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('sources.index'))->assertOk();
        $this->get(route('sources.create'))->assertOk();
        $this->get(route('sources.show', $clip->videoSource))->assertOk();
        $this->get(route('review.index'))->assertOk();
        $this->get(route('review.show', $clip))->assertOk();
        $this->get(route('schedule.index'))->assertOk();
        $this->get(route('channels.youtube'))->assertOk();
        $this->get(route('activity.index'))->assertOk();
        $this->get(route('failed-jobs.index'))->assertOk();
        $this->get(route('subtitle-templates.index'))->assertOk();
        $this->get(route('settings.index'))->assertOk();
    }

    public function test_livewire_components_render(): void
    {
        $user = $this->owner();
        $channel = $this->channelFor($user);
        [$clip] = $this->approvedClipFor($user, $channel);

        Livewire::actingAs($user)->test(Dashboard::class)->assertOk();
        Livewire::actingAs($user)->test(SourceList::class)->assertOk();
        Livewire::actingAs($user)->test(SourceUpload::class)->assertOk();
        Livewire::actingAs($user)->test(ReviewPanel::class, ['clip' => $clip])->assertOk();
        Livewire::actingAs($user)->test(ScheduleBoard::class)->assertOk();
        Livewire::actingAs($user)->test(ChannelConnect::class)->assertOk();
        Livewire::actingAs($user)->test(SettingsForm::class)->assertOk();
    }

    public function test_settings_can_be_saved_from_dashboard(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SettingsForm::class)
            ->set('dailyLimit', 12)
            ->set('slotStart', '09:00')
            ->set('slotEnd', '21:00')
            ->call('save')
            ->assertHasNoErrors();
    }
}
