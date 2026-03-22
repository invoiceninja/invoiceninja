@extends('portal.ninja2020.layout.app')

@section('meta_title', ctrans('texts.client_information'))

@section('header')
    <p class="leading-5 text-gray-500">{{ ctrans('texts.update_your_personal_info') }}</p>
@endsection

@section('body')
    @if(session()->has('missing_required_fields'))
        <div class="validation validation-fail">
            <p class="mb-3 font-semibold">{{ ctrans('texts.before_proceeding_with_payment_warning') }}:</p>

            <ul>
                @foreach(session()->get('missing_required_fields') as $field)
                    <li class="block">— {{ ctrans("texts.{$field}") }}</li>
                @endforeach
            </ul>

            <button onclick="window.history.back();" class="block mt-3 button button-link pl-0 ml-0 underline">{{ ctrans('texts.after_completing_go_back_to_previous_page') }}</button>
        </div>
    @endif

    <!-- Clients' name, website & logo -->
    @livewire('profile.settings.name-website-logo')

    <!-- Basic information: Contact's first & last name, e-mail address etc. -->
    @livewire('profile.settings.general')

    <!-- Client personal address -->
    @livewire('profile.settings.personal-address')

    <!-- Client shipping address -->
    @livewire('profile.settings.shipping-address')
@endsection
