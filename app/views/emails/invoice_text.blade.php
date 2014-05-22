{{ $clientName }},

{{ trans("texts.{$entityType}_message", ['amount' => $invoiceAmount]) }}
{{ $link }}

@if ($emailFooter)
{{ $emailFooter }}
@else
{{ trans('texts.email_signature') }}
{{ $accountName }}
@endif

{{ trans('texts.ninja_email_footer', ['site' => 'Invoice Ninja']) }}
https://www.invoiceninja.com