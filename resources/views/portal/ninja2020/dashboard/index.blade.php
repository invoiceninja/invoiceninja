@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.dashboard'))

@section('header')
    @if(!empty($client->getSetting('custom_message_dashboard')))
        @component('portal.ninja2020.components.message')
            {!! CustomMessage::client($client)
                ->company($client->company)
                ->message($client->getSetting('custom_message_dashboard')) !!}
        @endcomponent
    @endif
@endsection

@section('body')
    Coming soon.
@endsection
