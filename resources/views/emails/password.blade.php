@extends('emails.master_user')

@section('body')
    <div>
        {{ trans('texts.reset_password') }}
    </div>
    &nbsp;
    <div>
        <center>
            @include('partials.email_button', [
                'link' => URL::to(SITE_URL . "/password/reset/{$token}"),
                'field' => 'reset',
                'color' => '#36c157',
            ])
        </center>
    </div>
    &nbsp;
    <div>
        {{ trans('texts.email_signature') }}<br/>
        {{ trans('texts.email_from') }}
    </div>
    &nbsp;
    <div>
        {{ trans('texts.reset_password_footer', ['email' => env('CONTACT_EMAIL', CONTACT_EMAIL)]) }}
    </div>
@stop
