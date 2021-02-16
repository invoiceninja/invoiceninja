@component('email.template.master', ['design' => 'light', 'whitelabel' => false])

    @slot('header')
        @include('email.components.header', ['logo' => $logo])
    @endslot

    <p>{{ ctrans('texts.reset_password') }}</p>

    <a href="{{ $link }}" target="_blank" class="button">
       {{ ctrans('texts.reset') }}
    </a>

    <a href="{{ $link }}">{{ $link }}</a>
@endcomponent
