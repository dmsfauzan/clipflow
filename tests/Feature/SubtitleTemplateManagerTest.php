<?php

namespace Tests\Feature;

use App\Livewire\SubtitleTemplateManager;
use App\Models\SubtitleTemplate;
use App\Models\User;
use App\Services\SubtitleTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubtitleTemplateManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_and_activate_template(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SubtitleTemplateManager::class)
            ->set('name', 'Bold Creator')
            ->set('fontSize', 34)
            ->set('position', 'top')
            ->set('textColor', '#FFFFFF')
            ->set('outlineColor', '#000000')
            ->set('backgroundColor', '')
            ->set('makeDefault', true)
            ->call('save')
            ->assertHasNoErrors();

        $template = SubtitleTemplate::query()->where('name', 'Bold Creator')->firstOrFail();

        $this->assertTrue($template->is_default);
        $this->assertSame('Bold Creator', app(SubtitleTemplateService::class)->active()->name);
    }

    public function test_rejects_invalid_color(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SubtitleTemplateManager::class)
            ->set('name', 'Rusak')
            ->set('textColor', 'putih')
            ->call('save')
            ->assertHasErrors(['textColor']);
    }

    public function test_deleting_default_template_keeps_a_default_available(): void
    {
        $user = User::factory()->create();
        $service = app(SubtitleTemplateService::class);
        $first = $service->active();

        $second = SubtitleTemplate::create(array_merge(SubtitleTemplate::defaults(), [
            'name' => 'Kedua',
            'is_default' => false,
        ]));

        Livewire::actingAs($user)
            ->test(SubtitleTemplateManager::class)
            ->call('delete', $first->id);

        $this->assertDatabaseMissing('subtitle_templates', ['id' => $first->id]);
        $this->assertTrue(SubtitleTemplate::query()->where('is_default', true)->exists());
        $this->assertSame($second->name, $service->active()->name);
    }

    public function test_preset_fills_form_values(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SubtitleTemplateManager::class)
            ->call('applyPreset', 'highlight')
            ->assertSet('name', 'Highlight')
            ->assertSet('backgroundColor', '#14532D')
            ->assertSet('backgroundOpacity', 85)
            ->assertSet('position', 'bottom');
    }

    public function test_page_requires_authentication(): void
    {
        $this->get(route('subtitle-templates.index'))->assertRedirect(route('login'));
    }
}
