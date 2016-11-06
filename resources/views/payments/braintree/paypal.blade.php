@extends('payments.payment_method')

@section('payment_details')
    @parent

    {!! Former::open($url) !!}

    <h3>{{ trans('texts.paypal') }}</h3>

    <div>{{$details->firstName}} {{$details->lastName}}</div>
    <div>{{$details->email}}</div>

    <input type="hidden" name="sourceToken" value="{{$sourceId}}">
    <input type="hidden" name="first_name" value="{{$details->firstName}}">
    <input type="hidden" name="last_name" value="{{$details->lastName}}">
    <input type="hidden" name="email" value="{{$details->email}}">

    <p>&nbsp;</p>

    @if (isset($amount) && $client && $account->showTokenCheckbox())
        <input id="token_billing" type="checkbox" name="token_billing" {{ $account->selectTokenCheckbox() ? 'CHECKED' : '' }} value="1" style="margin-left:0px; vertical-align:top">
        <label for="token_billing" class="checkbox" style="display: inline;">{{ trans('texts.token_billing_braintree_paypal') }}</label>
        <span class="help-block" style="font-size:15px">
            {!! trans('texts.token_billing_secure', ['link' => link_to('https://www.braintreepayments.com/', 'Braintree', ['target' => '_blank'])]) !!}
        </span>
    @endif

    <p>&nbsp;</p>

    <center>
        @if(isset($amount))
            {!! Button::success(strtoupper(trans('texts.pay_now') . ' - ' . $account->formatMoney($amount, $client, CURRENCY_DECORATOR_CODE)  ))
                            ->submit()
                            ->large() !!}
        @else
            {!! Button::success(strtoupper(trans('texts.add_paypal_account') ))
                        ->submit()
                        ->large() !!}
        @endif
    </center>

    {!! Former::close() !!}

@stop
