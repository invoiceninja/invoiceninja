@component('email.template.master', ['design' => 'light', 'settings' => $settings])
    @slot('header')
        @include('email.components.header', ['logo' => 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png'])
    @endslot

    <h1>Quote approved!</h1>
    <p>We want to inform you that quote was approved.</p>

    <a href="https://invoiceninja.com" target="_blank" class="button">Visit Invoice Ninja</a>
@endcomponent
