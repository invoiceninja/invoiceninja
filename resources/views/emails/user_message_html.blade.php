@extends('emails.master_user')

@section('body')
    <div>
        {{ trans('texts.email_salutation', ['name' => $userName]) }}
    </div>
    &nbsp;
    <div>
        {!! $primaryMessage !!}
    </div>
    @if (! empty($secondaryMessage))
        &nbsp;
        <div>
            {!! $secondaryMessage !!}
        </div>
    @endif
    @if (! empty($invoiceLink))
        &nbsp;
        <div>
            <center>
                @include('partials.email_button', [
                    'link' => $invoiceLink,
                    'field' => "view_invoice",
                    'color' => '#0b4d78',
                ])
            </center>
        </div>
    @endif
    &nbsp;
    <div>
        {{ trans('texts.email_signature') }} <br/>
        {{ trans('texts.email_from') }}
    </div>
@stop
