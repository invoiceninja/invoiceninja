@component('email.template.master', ['design' => 'light'])
    @slot('header')
        @include('email.components.header', ['logo' => 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png'])
    @endslot

    {{ $message }}
@endcomponent
