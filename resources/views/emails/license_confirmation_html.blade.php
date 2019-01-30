@extends('emails.master_user')

@section('body')
    <div>
        {{ $client }},
    </div>
    &nbsp;
    <div>
        {{ trans('texts.payment_message', ['amount' => $amount]) }}
    </div>
    &nbsp;
    <div>
        {{ $license }}
    </div>
    &nbsp;
    <div>
        {{ trans('texts.email_signature') }}<br/>
        {{ trans('texts.email_from') }}
    </div>
@stop