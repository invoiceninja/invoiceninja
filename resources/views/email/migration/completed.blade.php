@component('email.template.master', ['design' => 'light', 'settings' => $settings])

    @slot('header')
        @include('email.components.header', ['logo' => 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png'])
    @endslot

    <h1>Migration completed</h1>
    <p>We're happy to inform you that migration has been completed successfully. It is ready for you to review it.</p>

    <a href="{{ url('/') }}" target="_blank" class="button">Visit portal</a>

    <p>Thank you, <br/> Invoice Ninja</p>
@endcomponent
