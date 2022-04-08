{!! ctrans('texts.migration_failed_label') !!}

{!! ctrans('texts.migration_failed') !!} {!! $company->present()->name() !!}

@if(\App\Utils\Ninja::isSelfHost() || $is_system)
    {!! $exception->getMessage() !!}
    {!! $content !!}
@else
    Please contact us at contact@invoiceninja.com for more information on this error.
@endif

