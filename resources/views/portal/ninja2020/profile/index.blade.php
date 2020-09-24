@extends('portal.ninja2020.layout.app')

@section('meta_title', ctrans('texts.client_information'))

@section('header')
    <p class="leading-5 text-gray-500">{{ ctrans('texts.update_your_personal_info') }}</p>
@endsection

@section('body')
    <!-- Basic information: first & last name, e-mail address etc. -->
    @livewire('profile.settings.general')
    
    <!-- Name, website & logo -->
    @livewire('profile.settings.name-website-logo')

    <!-- Client personal address -->
    @livewire('profile.settings.personal-address', ['countries' => $countries])

    <!-- Client shipping address -->
    @livewire('profile.settings.shipping-address', ['countries' => $countries])
@endsection
