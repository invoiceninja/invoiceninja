@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.downloads'))

@section('header')
    @if($client->getSetting('client_portal_enable_uploads'))
        @component('portal.ninja2020.upload.index') @endcomponent
    @endif

    <script src="{{ asset('js/clients/shared/multiple-downloads.js') }}"></script>
@endsection

@section('body')
    <form action="{{ route('client.downloads.multiple') }}" method="post" id="multiple-downloads">
        @csrf
    </form>
    @livewire('downloads-table')
@endsection