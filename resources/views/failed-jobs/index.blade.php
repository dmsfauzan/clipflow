@extends('layouts.app')

@section('title', 'Job Gagal — clipflow.')
@section('heading', 'Job Gagal')

@section('content')
    <div class="space-y-6">
        <x-page-header title="Job gagal" description="Job yang gagal setelah semua percobaan. Periksa penyebabnya sebelum mencoba ulang." />

        @if ($failedJobs->isEmpty())
            <x-empty-state title="Tidak ada job gagal" description="Antrean bersih. Job yang gagal akan muncul di sini beserta pesan error-nya." icon="✓" />
        @else
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-left text-sm">
                        <thead class="border-b border-slate-100 text-[10px] font-bold uppercase tracking-wide text-slate-400">
                            <tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Queue</th><th class="px-5 py-3">Job</th><th class="px-5 py-3">Error</th><th class="px-5 py-3 text-right">Aksi</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($failedJobs as $job)
                                <tr class="align-top hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-400">{{ \Illuminate\Support\Carbon::parse($job->failed_at)->format('d M Y H:i') }}</td>
                                    <td class="px-5 py-3"><x-status-badge :label="$job->queue" tone="slate" /></td>
                                    <td class="px-5 py-3">
                                        <p class="text-xs font-semibold text-slate-700">{{ $job->job_name }}</p>
                                        <p class="mt-1 font-mono text-[10px] text-slate-400">{{ $job->uuid }}</p>
                                    </td>
                                    <td class="max-w-md px-5 py-3">
                                        <p class="line-clamp-3 text-xs text-rose-700">{{ \Illuminate\Support\Str::limit($job->exception, 320) }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            <form method="POST" action="{{ route('failed-jobs.retry', $job->uuid) }}">
                                                @csrf
                                                <button type="submit" class="btn-secondary !px-3 !py-1.5 text-xs">Retry</button>
                                            </form>
                                            <form method="POST" action="{{ route('failed-jobs.destroy', $job->uuid) }}" onsubmit="return confirm('Hapus job gagal ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-danger !px-3 !py-1.5 text-xs">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-4">{{ $failedJobs->links() }}</div>
            </div>

            <div class="card card-pad">
                <p class="text-xs leading-relaxed text-slate-500">Retry akan mengirim ulang job apa adanya. Jika penyebabnya data atau kredensial yang salah, perbaiki dulu agar tidak gagal lagi. Worker pipeline harus aktif agar retry diproses.</p>
            </div>
        @endif
    </div>
@endsection
