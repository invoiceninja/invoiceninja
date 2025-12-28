@component('email.template.master', ['design' => 'light', 'settings' => $settings])
    @slot('header')
        @include('email.components.header', ['logo' => $company ? $company->present()->logo() : ''])
    @endslot

    <h1>Payment for your invoice has been completed!</h1>
    <p>We want to inform you that payment was completed for your invoice.</p>
@endcomponent
