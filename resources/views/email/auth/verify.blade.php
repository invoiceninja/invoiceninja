@component('mail::layout')

{{-- Header --}}
@slot('header')
@component('mail::header', ['url' => config('app.url')])
Header Title
@endcomponent
@endslot

{{-- Body --}}
{{ $user }}
@lang('texts.confirmation_message')

@component('mail::button', ['url' => url("/user/confirm/{$user->confirmation_code} ")])
@lang('texts.confirm')
@endcomponent

{{-- Subcopy --}}
@isset($subcopy)
@slot('subcopy')
@component('mail::subcopy')
{{ $subcopy }}
@endcomponent
@endslot
@endisset


{{-- Footer --}}
@slot('footer')
@component('mail::footer')
© {{ date('Y') }} {{ config('ninja.app_name') }}.
@endcomponent
@endslot

@endcomponent