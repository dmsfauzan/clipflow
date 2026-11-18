<?php

namespace App\Livewire;

use App\Enums\VideoSourceStatus;
use App\Models\VideoSource;
use App\Services\SourceRetryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\WithPagination;

class SourceList extends AuthenticatedComponent
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public bool $includeDemo = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function retry(int $sourceId, SourceRetryService $retries): void
    {
        $source = VideoSource::query()
            ->where('user_id', auth()->id())
            ->findOrFail($sourceId);

        try {
            $retries->retry($source);
        } catch (\Throwable $exception) {
            $this->addError('retry', $exception->getMessage());

            return;
        }

        $this->dispatch('toast', message: 'Sumber dikirim ulang ke antrean pemrosesan.');
    }

    public function render(): View
    {
        $query = VideoSource::query()
            ->where('user_id', auth()->id())
            ->when(! $this->includeDemo, fn ($query) => $query->where('is_demo', false))
            ->when($this->search !== '', fn ($query) => $query->where('original_name', 'like', '%'.$this->search.'%'))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->withCount('clips')
            ->orderByDesc('created_at');

        /** @var LengthAwarePaginator $sources */
        $sources = $query->paginate(12);

        return view('livewire.source-list', [
            'sources' => $sources,
            'statuses' => VideoSourceStatus::cases(),
            'hasDemo' => VideoSource::query()->where('user_id', auth()->id())->where('is_demo', true)->exists(),
        ]);
    }
}
