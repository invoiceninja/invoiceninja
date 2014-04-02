{{ trans('texts.email_salutation', ['name' => $userName]) }}

{{ trans('texts.notification_paid', ['amount' => $paymentAmount, 'client' => $clientName, 'invoice' => $invoiceNumber]) }}

{{ trans('texts.invoice_link_message') }}
{{ $invoiceLink }}

{{ trans('texts.email_signature') }}
{{ trans('texts.email_from') }}

{{ trans('texts.user_email_footer') }}