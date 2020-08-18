@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.downloads'))

@section('header')
    @component('portal.ninja2020.upload.index')@endcomponent
@endsection

@section('body')
    @livewire('downloads-table')
@endsection