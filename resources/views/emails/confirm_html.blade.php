@extends('emails.master_user')

@section('markup')
    @if (!$invitationMessage)
        @include('emails.confirm_action', ['user' => $user])
    @endif
@stop

@section('body')
    <h2>{{ trans('texts.confirmation_header') }}</h2>
    <div>
        {{ $invitationMessage . trans('texts.button_confirmation_message') }}
    </div>
    &nbsp;
    <div>
        <center>
            @include('partials.email_button', [
                'link' => URL::to("user/confirm/{$user->confirmation_code}"),
                'field' => 'confirm',
                'color' => '#36c157',
            ])
        </center>
    </div>
    &nbsp;
    <div>
        {{ trans('texts.email_signature') }}<br/>
        {{ trans('texts.email_from') }}
    </div>
@stop