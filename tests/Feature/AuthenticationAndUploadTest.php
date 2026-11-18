<?php

namespace Tests\Feature;

use App\Jobs\ProbeVideoSource;
use App\Livewire\SourceUpload;
use App\Models\User;
use App\Models\VideoSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationAndUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/review')->assertRedirect('/login');
        $this->get('/sources/create')->assertRedirect('/login');
    }

    public function test_upload_requires_rights_confirmation_and_valid_extension(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(SourceUpload::class)
            ->set('video', UploadedFile::fake()->create('source.txt', 10, 'text/plain'))
            ->set('rightsConfirmed', true)
            ->call('save')
            ->assertHasErrors(['video']);

        $this->assertDatabaseCount('video_sources', 0);

        Livewire::test(SourceUpload::class)
            ->set('video', UploadedFile::fake()->create('source.mp4', 10, 'video/mp4'))
            ->call('save')
            ->assertHasErrors(['rightsConfirmed']);

        $this->assertDatabaseCount('video_sources', 0);
    }

    public function test_valid_upload_is_stored_privately_and_queued(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(SourceUpload::class)
            ->set('video', UploadedFile::fake()->create('podcast.mp4', 512, 'video/mp4'))
            ->set('rightsConfirmed', true)
            ->call('save')
            ->assertHasNoErrors();

        $source = VideoSource::query()->firstOrFail();

        $this->assertSame($user->id, $source->user_id);
        $this->assertSame('uploaded', $source->status->value);
        $this->assertStringStartsWith('video-sources/'.$user->id.'/', $source->path);
        Storage::disk('local')->assertExists($source->path);
        Queue::assertPushed(ProbeVideoSource::class);
    }

    public function test_upload_size_limit_is_enforced(): void
    {
        config(['clipflow.media.max_upload_bytes' => 1024 * 1024]);

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(SourceUpload::class)
            ->set('video', UploadedFile::fake()->create('besar.mp4', 2048, 'video/mp4'))
            ->set('rightsConfirmed', true)
            ->call('save')
            ->assertHasErrors(['video']);

        $this->assertDatabaseCount('video_sources', 0);
    }
}
