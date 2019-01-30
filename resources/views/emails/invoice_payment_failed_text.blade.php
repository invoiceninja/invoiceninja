{!! trans('texts.email_salutation', ['name' => $userName]) !!}

{!! trans("texts.notification_invoice_payment_failed", ['amount' => $paymentAmount, 'client' => $clientName, 'invoice' => $invoiceNumber]) !!}

{!! $payment->gateway_error !!}

{!! trans('texts.email_signature') !!}
{!! trans('texts.email_from') !!}