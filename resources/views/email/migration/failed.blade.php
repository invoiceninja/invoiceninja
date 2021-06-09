@component('email.template.admin', ['logo' => $logo, 'settings' => $settings])
    <div class="center">
        <h1>{{ ctrans('texts.migration_failed_label') }}</h1>
        <p>{{ ctrans('texts.migration_failed') }} {{ $company->present()->name() }}</p>

        <pre>
    	    @if(\App\Utils\Ninja::isHosted())
                {!! $exception->getMessage() !!}
                {!! $content !!}
            @else
                {!! $exception->getMessage() !!}
                {!! $content !!}
            @endif
        </pre>
    </div>
@endcomponent
