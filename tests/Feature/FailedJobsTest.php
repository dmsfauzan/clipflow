<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FailedJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('failed-jobs.index'))->assertRedirect(route('login'));
    }

    public function test_owner_can_see_failed_jobs(): void
    {
        $this->insertFailedJob();

        $this->actingAs(User::factory()->create())
            ->get(route('failed-jobs.index'))
            ->assertOk()
            ->assertSee('App\\Jobs\\RenderClip')
            ->assertSee('RuntimeException: boom');
    }

    public function test_owner_can_delete_failed_job(): void
    {
        $uuid = $this->insertFailedJob();

        $this->actingAs(User::factory()->create())
            ->delete(route('failed-jobs.destroy', $uuid))
            ->assertRedirect();

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
    }

    public function test_invalid_uuid_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->delete(route('failed-jobs.destroy', 'bukan-uuid'))
            ->assertNotFound();
    }

    private function insertFailedJob(): string
    {
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'pipeline',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\RenderClip', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call']),
            'exception' => "RuntimeException: boom\n#0 {main}",
            'failed_at' => now(),
        ]);

        return $uuid;
    }
}
