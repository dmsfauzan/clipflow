@extends('layouts.app')

@section('title', 'Tambah sumber video — Clipflow')
@section('heading', 'Tambah sumber')

@section('content')
    <div class="space-y-6">
        <x-page-header title="Unggah video sumber" description="File disimpan di storage privat dengan nama acak. Pemrosesan berjalan lewat antrean, bukan di request browser.">
            <x-slot:actions><a href="{{ route('sources.index') }}" class="btn-secondary">← Kembali ke daftar</a></x-slot:actions>
        </x-page-header>

        <livewire:source-upload />
    </div>
@endsection
