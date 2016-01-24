@extends('emails.master_user')

@section('body')
    <div>
        {{ trans('texts.email_salutation', ['name' => $userName]) }}
    </div>
    &nbsp;
    <div>
        {{ trans("texts.notification_{$entityType}_bounced", ['contact' => $contactName, 'invoice' => $invoiceNumber]) }}
    </div>
    &nbsp;
    <div>
        {{ $emailError }}
    </div>
    &nbsp;
    <div>
        {{ trans('texts.email_signature') }} <br/>
        {{ trans('texts.email_from') }}
    </div>
@stop