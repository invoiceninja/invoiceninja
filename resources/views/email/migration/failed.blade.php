@component('email.template.master', ['design' => 'light', 'settings' => $settings])
    @slot('header')
        @include('email.components.header', ['logo' => 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png'])
    @endslot

    <h1>Whoops, migration failed for {{ $company->present()->name() }}.</h1>
    <p>Looks like your migration failed. Here's the error message:</p>

    <pre>
    	@if(\App\Utils\Ninja::isSelfHost())
	        {!! $exception->getMessage() !!}
            {!! $content !!}
    	@else
        <p>Please contact us at contact@invoiceninja.com for more information on this error.</p>
        @endif
    </pre>
@endcomponent
