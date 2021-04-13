@component('email.template.master', ['design' => 'light'])
    @slot('header')
        @include('email.components.header', ['logo' => 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png'])
    @endslot

    <p>Hello,</p>

    <p>Connecting bank accounts require verification. Stripe will automatically sends two
        small deposits for this purpose. These deposits take 1-2 business days to appear on the customer's online
        statement.
    </p>

    <p>Thank you!</p>
@endcomponent
