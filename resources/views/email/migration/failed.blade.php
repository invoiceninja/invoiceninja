@component('email.template.master', ['design' => 'light', 'settings' => $settings])
    @slot('header')
        @include('email.components.header', ['logo' => 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png'])
    @endslot

    <h1>Whoops, migration failed.</h1>
    <p>Looks like your migration failed. Here's the error message:</p>

    <pre>
    	@if(Ninja::isHosted())
    		There was a problem with your migration, please send us an email contact@invoiceninja.com
    	@else
        {!! $exception->getMessage() !!}
        {!! $content !!}
        @endif
    </pre>
@endcomponent
