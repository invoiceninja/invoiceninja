@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.downloads'))

@section('header')
    {{ dd(auth('contact')->user()->client) }}
    @if($client->getSetting('client_portal_enable_uploads'))
        @component('portal.ninja2020.upload.index') @endcomponent
    @endif
@endsection

@section('body')
    @livewire('downloads-table')
@endsection