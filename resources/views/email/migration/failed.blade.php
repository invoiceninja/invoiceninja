@component('email.template.master', ['design' => 'light', 'settings' => $settings])
    @slot('header')
        @include('email.components.header', ['logo' => 'https://www.invoiceninja.com/wp-content/uploads/2015/10/logo-white-horizontal-1.png'])
    @endslot

    <h1>Whoops, migration failed for {{ $company->present()->name() }}.</h1>
    <p>Looks like your migration failed. Here's the error message:</p>

    <pre>
    	@if(\App\Utils\Ninja::isHosted())
	        {!! $exception->getMessage() !!}
            {!! $content !!}
    	@else
        {!! $exception->getMessage() !!}
        {!! $content !!}
        @endif
    </pre>
@endcomponent
