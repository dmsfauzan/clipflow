<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class PrivateMediaTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    public function test_guest_is_redirected(): void
    {
        $user = User::factory()->create();
        $clip = $this->renderedClipFor($user);

        $this->get(route('media.clip.preview', $clip))->assertRedirect(route('login'));
    }

    public function test_other_user_gets_forbidden(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $clip = $this->renderedClipFor($owner);

        $this->actingAs($other)
            ->get(route('media.clip.preview', $clip))
            ->assertForbidden();
    }

    public function test_owner_can_download_with_range_support(): void
    {
        $user = User::factory()->create();
        $clip = $this->renderedClipFor($user);

        Storage::fake('local');
        Storage::disk('local')->put($clip->rendered_path, str_repeat('a', 1000));

        $this->actingAs($user)
            ->get(route('media.clip.preview', $clip), ['Range' => 'bytes=0-99'])
            ->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Range', 'bytes 0-99/1000')
            ->assertHeader('Content-Length', '100');
    }

    public function test_missing_file_returns_404(): void
    {
        $user = User::factory()->create();
        $clip = $this->renderedClipFor($user);
        Storage::fake('local');

        $this->actingAs($user)
            ->get(route('media.clip.download', $clip))
            ->assertNotFound();
    }
}
