{!! trans('texts.email_salutation', ['name' => $userName]) !!}

{!! strip_tags($primaryMessage) !!}

@if (! empty($secondaryMessage))
    {!! strip_tags($secondaryMessage) !!}

@endif

@if (! empty($invoiceLink))
    {!! $invoiceLink !!}
@endif

{!! trans('texts.email_signature') !!}
{!! trans('texts.email_from') !!}
