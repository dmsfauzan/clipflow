<div {{ $attributes->merge(['class' => 'card flex flex-col items-center justify-center gap-2 px-6 py-14 text-center']) }}>
    <div class="mb-2 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400">{{ $icon ?? '◌' }}</div>
    <p class="text-base font-bold text-slate-900">{{ $title }}</p>
    <p class="max-w-md text-sm leading-relaxed text-slate-400">{{ $description }}</p>
    @isset($actions)
        <div class="mt-4">{{ $actions }}</div>
    @endisset
</div>
