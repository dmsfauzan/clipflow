@extends('layouts.app')

@section('title', $clip->title.' — Review — Clipflow')
@section('heading', 'Review klip')

@section('content')
    <livewire:review-panel :clip="$clip" />
@endsection
