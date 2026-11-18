@extends('layouts.app')

@section('title', 'Aktivitas — Clipflow')
@section('heading', 'Aktivitas')

@section('content')
    <div class="space-y-6">
        <x-page-header title="Aktivitas sistem" description="Jejak unggah, transkripsi, render, approval, jadwal, upload, dan perubahan pengaturan." />

        <form method="GET" action="{{ route('activity.index') }}" class="card card-pad grid gap-3 md:grid-cols-[1fr_240px_auto]">
            <div><label class="label" for="activity-q">Cari aktivitas</label><input id="activity-q" name="q" value="{{ $search }}" class="input" placeholder="Klip, sumber, deskripsi..."></div>
            <div><label class="label" for="activity-event">Jenis peristiwa</label><select id="activity-event" name="event" class="input"><option value="">Semua</option>@foreach ($events as $eventName)<option value="{{ $eventName }}" @selected($selectedEvent === $eventName)>{{ $eventName }}</option>@endforeach</select></div>
            <div class="flex items-end"><button type="submit" class="btn-primary">Tampilkan</button></div>
        </form>

        @if ($logs->isEmpty())
            <x-empty-state title="Belum ada aktivitas" description="Setelah pipeline dan approval berjalan, jejaknya akan muncul di sini." icon="≡" />
        @else
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="border-b border-slate-100 text-[10px] font-bold uppercase tracking-wide text-slate-400">
                            <tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Peristiwa</th><th class="px-5 py-3">Deskripsi</th><th class="px-5 py-3">Oleh</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($logs as $log)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-5 py-3 text-xs text-slate-400">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                                    <td class="px-5 py-3"><span class="badge bg-slate-100 text-slate-600 ring-1 ring-slate-200">{{ $log->event }}</span></td>
                                    <td class="max-w-xl px-5 py-3 text-slate-600">{{ $log->description ?: '—' }}</td>
                                    <td class="px-5 py-3 text-xs text-slate-400">{{ $log->user?->name ?? 'Sistem' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-4">{{ $logs->links() }}</div>
            </div>
        @endif
    </div>
@endsection
