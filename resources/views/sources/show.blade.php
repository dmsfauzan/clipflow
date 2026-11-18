@extends('layouts.app')

@section('title', $source->original_name.' — Clipflow')
@section('heading', 'Detail sumber video')

@section('content')
    <livewire:source-detail :source="$source" />
@endsection
