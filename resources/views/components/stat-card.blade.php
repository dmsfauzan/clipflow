<div {{ $attributes->merge(['class' => 'card card-pad']) }}>
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p>
            <p class="mt-2 text-3xl font-extrabold tracking-tight text-slate-950">{{ $value }}</p>
            @isset($hint)
                <p class="mt-2 text-xs leading-relaxed text-slate-400">{{ $hint }}</p>
            @endisset
        </div>
        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-lg text-emerald-900">{{ $icon ?? '▤' }}</div>
    </div>
    {{ $slot ?? '' }}
</div>
