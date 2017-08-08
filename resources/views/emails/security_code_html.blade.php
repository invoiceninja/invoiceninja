@extends('emails.master_user')

@section('body')
    <div>
        {{ trans('texts.email_salutation', ['name' => $userName]) }}
    </div>
    &nbsp;
    <div>
        {{ trans("texts.security_code_email_line1") }}
    </div>
    &nbsp;
    <div>
        <center><h2>{{ $code }}</h2></center>
    </div>
    &nbsp;
    <div>
        {{ trans("texts.security_code_email_line2") }}
    </div>
    &nbsp;
    <div>
        {{ trans('texts.email_signature') }} <br/>
        {{ trans('texts.email_from') }}
    </div>
@stop
