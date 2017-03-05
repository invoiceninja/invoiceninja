@extends('emails.master_user')

@section('markup')
    @if ($account->emailMarkupEnabled())
        @include('emails.partials.user_view_action')
    @endif
@stop

@section('body')
    <div>
        {{ trans('texts.email_salutation', ['name' => $userName]) }}
    </div>
    &nbsp;
    <div>
        {{ trans("texts.notification_invoice_payment_failed", ['amount' => $paymentAmount, 'client' => $clientName, 'invoice' => $invoiceNumber]) }}
    </div>
    &nbsp;
    <div>
        {{ $payment->gateway_error }}
    </div>
    &nbsp;
    <div>
        {{ trans('texts.email_signature') }} <br/>
        {{ trans('texts.email_from') }}
    </div>
@stop