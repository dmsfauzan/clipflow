<?php

namespace Tests\Unit;

use App\Exceptions\ClipSelectionException;
use App\Models\Transcript;
use App\Models\VideoSource;
use App\Services\ClipSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsClipflowData;
use Tests\TestCase;

class ClipSelectionServiceTest extends TestCase
{
    use BuildsClipflowData, RefreshDatabase;

    public function test_invalid_ai_timestamps_are_rejected_without_creating_clip(): void
    {
        $this->fakeOllama([
            'clips' => [
                ['start' => -3, 'end' => 30, 'title' => 'Invalid', 'caption' => 'No', 'reason' => 'Bad', 'score' => 90],
                ['start' => 0, 'end' => 99999, 'title' => 'Out', 'caption' => 'No', 'reason' => 'Bad', 'score' => 90],
                ['start' => 5, 'end' => 10, 'title' => 'Terlalu pendek', 'caption' => 'No', 'reason' => 'Bad', 'score' => 90],
                ['start' => 15, 'end' => 45, 'title' => 'Bukan batas segmen', 'caption' => 'No', 'reason' => 'Bad', 'score' => 90],
            ],
        ]);

        [$source, $transcript] = $this->sourceWithTranscript();

        $candidates = app(ClipSelectionService::class)->selectForSource($source, $transcript);

        $this->assertSame([], $candidates);
        $this->assertDatabaseCount('clips', 0);
    }

    public function test_valid_candidates_are_snapped_to_segment_boundaries_and_overlap_is_skipped(): void
    {
        $this->fakeOllama([
            'clips' => [
                ['start' => 61, 'end' => 89, 'title' => 'Terlalu', 'caption' => 'C', 'reason' => 'R', 'score' => 150],
                ['start' => 61, 'end' => 89, 'title' => 'Duplikat', 'caption' => 'C', 'reason' => 'R', 'score' => 50],
                ['start' => 0, 'end' => 30, 'title' => 'Kandidat valid', 'caption' => 'Caption valid', 'reason' => 'Alasan valid', 'score' => 42],
            ],
        ]);

        [$source, $transcript] = $this->sourceWithTranscript();

        $candidates = app(ClipSelectionService::class)->selectForSource($source, $transcript);

        $this->assertCount(2, $candidates);
        $this->assertSame(60.0, $candidates[0]['start']);
        $this->assertSame(90.0, $candidates[0]['end']);
        $this->assertSame(100.0, $candidates[0]['score']);
        $this->assertSame(0.0, $candidates[1]['start']);
        $this->assertSame('Kandidat valid', $candidates[1]['title']);
    }

    public function test_invalid_json_response_raises_clear_error(): void
    {
        Http::fake([
            '127.0.0.1:11434/*' => Http::response([
                'message' => ['content' => 'ini bukan json'],
            ]),
        ]);

        [$source, $transcript] = $this->sourceWithTranscript();

        $this->expectException(ClipSelectionException::class);

        app(ClipSelectionService::class)->selectForSource($source, $transcript);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function test_one_failed_chunk_does_not_fail_whole_selection(): void
    {
        config(['clipflow.ai.chunk_seconds' => 40]);

        Http::fakeSequence('127.0.0.1:11434/*')
            ->push(['error' => 'gateway down'], 500)
            ->push([
                'message' => [
                    'content' => json_encode([
                        'clips' => [
                            ['start' => 60, 'end' => 90, 'title' => 'Dari chunk kedua', 'caption' => 'C', 'reason' => 'R', 'score' => 50],
                        ],
                    ], JSON_THROW_ON_ERROR),
                ],
            ], 200);

        [$source, $transcript] = $this->sourceWithTranscript();

        $candidates = app(ClipSelectionService::class)->selectForSource($source, $transcript);

        $this->assertCount(1, $candidates);
        $this->assertSame(60.0, $candidates[0]['start']);
    }

    private function fakeOllama(array $payload): void
    {
        Http::fake([
            '127.0.0.1:11434/*' => Http::response([
                'message' => ['content' => json_encode($payload, JSON_THROW_ON_ERROR)],
            ]),
        ]);
    }

    /**
     * @return array{0: VideoSource, 1: Transcript}
     */
    private function sourceWithTranscript(): array
    {
        $user = $this->owner();
        $source = $this->sourceFor($user, ['duration_seconds' => 120]);

        $transcript = $source->transcript()->create([
            'language' => 'id',
            'model' => 'test',
            'full_text' => 'Konten test',
            'segments' => [
                ['start' => 0, 'end' => 30, 'text' => 'Segmen pertama untuk kandidat klip.'],
                ['start' => 30, 'end' => 60, 'text' => 'Segmen kedua untuk kandidat klip.'],
                ['start' => 60, 'end' => 90, 'text' => 'Segmen ketiga untuk kandidat klip.'],
                ['start' => 90, 'end' => 120, 'text' => 'Segmen keempat untuk kandidat klip.'],
            ],
            'status' => 'completed',
        ]);

        return [$source->fresh(), $transcript];
    }
}
