<?php

namespace App\Livewire;

use App\Enums\ClipStatus;
use App\Models\Clip;
use App\Models\PublishingChannel;
use App\Services\ActivityLogger;
use App\Services\ApprovalService;
use App\Services\SchedulingService;
use App\Services\SettingsService;
use App\Services\YouTubeOAuthService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\WithPagination;

class ReviewList extends AuthenticatedComponent
{
    use WithPagination;

    public string $search = '';

    public string $status = 'review';

    public array $selectedIds = [];

    #[Validate('required')]
    public ?int $channelId = null;

    #[Validate('required')]
    public string $privacy = 'private';

    public bool $madeForKids = false;

    public bool $includeDemo = false;

    public function mount(SettingsService $settings): void
    {
        $this->privacy = (string) $settings->get('publishing.default_privacy', 'private');

        $channel = PublishingChannel::query()
            ->where('user_id', auth()->id())
            ->where('status', 'connected')
            ->where('is_active', true)
            ->first();

        $this->channelId = $channel?->id;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Approve hanya klip yang dipilih pengguna.
     */
    public function approveSelected(
        ApprovalService $approvals,
        SchedulingService $scheduling,
        SettingsService $settings,
        ActivityLogger $activity,
    ): void {
        $this->validate([
            'channelId' => ['required', 'integer'],
            'privacy' => ['required', 'in:private,unlisted,public'],
            'selectedIds' => ['required', 'array', 'min:1'],
        ], [
            'selectedIds.required' => 'Pilih minimal satu klip untuk batch approval.',
            'channelId.required' => 'Pilih channel tujuan terlebih dahulu.',
        ]);

        $channel = PublishingChannel::query()
            ->where('user_id', auth()->id())
            ->whereKey($this->channelId)
            ->first();

        if (! $channel || ! $channel->isConnected()) {
            $this->addError('channelId', 'Channel belum terhubung ke YouTube.');

            return;
        }

        $clips = Clip::query()
            ->where('user_id', auth()->id())
            ->whereKey($this->selectedIds)
            ->get(['id', 'user_id', 'title', 'status', 'caption']);

        $approved = 0;
        $errors = [];
        $autoSchedule = $settings->boolean('publishing.auto_schedule');

        foreach ($clips as $clip) {
            if ($clip->status !== ClipStatus::Review) {
                $errors[] = sprintf('"%s": hanya klip berstatus review yang dapat disetujui.', $clip->title);

                continue;
            }

            try {
                $approval = $approvals->approve($clip, auth()->user(), $channel, $this->privacy, $this->madeForKids);

                if ($autoSchedule) {
                    $scheduling->schedule($clip, $approval, null, auth()->user());
                }

                $approved++;
            } catch (\Throwable $exception) {
                $errors[] = sprintf('"%s": %s', $clip->title, $exception->getMessage());
            }
        }

        $activity->log(
            'clip.batch_approved',
            sprintf('Batch approval: %d klip disetujui, %d diabaikan.', $approved, count($errors)),
        );

        $this->selectedIds = [];

        if ($errors !== []) {
            $this->addError('selectedIds', implode(' | ', array_slice($errors, 0, 3)));

            return;
        }

        $this->dispatch('toast', message: $approved.' klip disetujui. Approval wajib sebelum upload otomatis.');
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    public function render(): View
    {
        $query = Clip::query()
            ->where('user_id', auth()->id())
            ->select(['id', 'user_id', 'video_source_id', 'transcript_id', 'title', 'caption', 'reason', 'ai_score', 'status',
                'start_seconds', 'end_seconds', 'duration_seconds', 'rendered_path', 'thumbnail_path', 'rendered_size_bytes',
                'rendered_width', 'rendered_height', 'media_hash', 'render_started_at', 'rendered_at', 'rejected_at',
                'reject_reason', 'failure_message', 'created_at', 'updated_at'])
            ->with('videoSource')
            ->when(! $this->includeDemo, fn ($query) => $query->whereHas('videoSource', fn ($q) => $q->where('is_demo', false)))
            ->when($this->status !== '' && $this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($q) => $q->where('title', 'like', '%'.$this->search.'%')->orWhere('caption', 'like', '%'.$this->search.'%')))
            ->orderByDesc('created_at');

        /** @var LengthAwarePaginator $clips */
        $clips = $query->paginate(12);

        return view('livewire.review-list', [
            'clips' => $clips,
            'statuses' => ClipStatus::cases(),
            'channels' => PublishingChannel::query()->where('user_id', auth()->id())->get(),
            'autoSchedule' => app(SettingsService::class)->boolean('publishing.auto_schedule'),
            'oauthConfigured' => app(YouTubeOAuthService::class)->isConfigured(),
        ]);
    }
}
