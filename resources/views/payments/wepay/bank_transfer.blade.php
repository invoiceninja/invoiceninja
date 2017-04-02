@extends('payments.bank_transfer')

@section('payment_details')

    {!! Former::vertical_open($url) !!}

    <h3>{{ trans('texts.bank_account') }}</h3>

    @if (!empty($details))
        <div>{{$details->bank_account_name}}</div>
        <div>&bull;&bull;&bull;&bull;&bull;{{$details->bank_account_last_four}}</div>
    @endif

    {!! Former::checkbox('authorize_ach')
            ->text(trans('texts.ach_authorization', ['company'=>$account->getDisplayName(), 'email' => $account->work_email]))
            ->label(' ')
            ->value(1) !!}

    {!! Former::checkbox('tos_agree')
            ->text(trans('texts.wepay_payment_tos_agree', [
            'terms' => '<a href="https://go.wepay.com/terms-of-service-us" target="_blank">'.trans('texts.terms_of_service').'</a>',
            'privacy_policy' => '<a href="https://go.wepay.com/privacy-policy-us" target="_blank">'.trans('texts.privacy_policy').'</a>',
            ]))
            ->help(trans('texts.payment_processed_through_wepay'))
            ->label(' ')
            ->value(1) !!}

    <input type="hidden" name="sourceToken" value="{{$sourceId}}">

    <p>&nbsp;</p>

    <center>
        {!! Button::normal(strtoupper(trans('texts.cancel')))->large()->asLinkTo($invitation->getLink()) !!}
        &nbsp;&nbsp;
        @if(isset($amount) && empty($paymentMethodPending))
            {!! Button::success(strtoupper(trans('texts.pay_now') . ' - ' . $account->formatMoney($amount, $client, CURRENCY_DECORATOR_CODE)  ))
                            ->submit()
                            ->large() !!}
        @else
            {!! Button::success(strtoupper(trans('texts.add_bank_account') ))
                        ->submit()
                        ->large() !!}
        @endif
    </center>

    {!! Former::close() !!}

@stop
