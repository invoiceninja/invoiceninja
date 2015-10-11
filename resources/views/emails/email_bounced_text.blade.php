{!! trans('texts.email_salutation', ['name' => $userName]) !!}

{!! trans("texts.notification_{$entityType}_bounced", ['contact' => $contactName, 'invoice' => $invoiceNumber]) !!}

{!! $emailError !!}

{!! trans('texts.email_signature') !!}
{!! trans('texts.email_from') !!}