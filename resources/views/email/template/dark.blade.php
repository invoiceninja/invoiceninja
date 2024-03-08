@component('email.template.master', ['design' => 'dark', 'settings' => $settings, 'whitelabel' => $whitelabel])

    @slot('header')
        @include('email.components.header', ['logo' => $company->present()->logo($settings)])
    @endslot

    {!! $body !!}

@if($signature)
<br>
<br>
<p>
{!! nl2br($signature) !!}
</p>
@endif

@isset($email_preferences)
<p>
    <a href="{{ $email_preferences }}">
        {{ ctrans('texts.email_preferences') }}
    </a>
</p>
@endif

@endcomponent
