@component('email.template.admin', ['logo' => $logo, 'settings' => $settings])
    <div class="center">
        <h1>{{ ctrans('texts.migration_failed_label') }}</h1>
        <p>{{ ctrans('texts.migration_failed') }} {{ $company->present()->name() }}</p>

        <pre>
            @if(\App\Utils\Ninja::isSelfHost() || $is_system)
                {!! $exception->getMessage() !!}
                {!! $content !!}
            @else
                @if($special_message)
                @endif
                <p>Please contact us at contact@invoiceninja.com for more information on this error.</p>
            @endif
        </pre>
    </div>
@endcomponent
