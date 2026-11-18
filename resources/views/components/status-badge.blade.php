@props(['label', 'tone' => 'slate'])

@php
    $tones = [
        'emerald' => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200',
        'amber' => 'bg-amber-50 text-amber-800 ring-1 ring-amber-200',
        'sky' => 'bg-sky-50 text-sky-800 ring-1 ring-sky-200',
        'rose' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
        'violet' => 'bg-violet-50 text-violet-800 ring-1 ring-violet-200',
        'slate' => 'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'badge '.$tones[$tone] ?? $tones['slate']]) }}>{{ $label }}</span>
