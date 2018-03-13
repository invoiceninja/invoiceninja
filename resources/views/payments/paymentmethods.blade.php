@extends('public.header')

@section('content')
    <div class="container main-container">

        <p>&nbsp;</p>

        <div class="row">
            <div class="col-xs-12">
                <div class="pull-left">
                    @include('payments.paymentmethods_list')
                </div>
                <div class="pull-right">
                    @if (! empty($account) && $account->enable_client_portal || $account->enable_client_portal_dashboard)
                        {!! Button::success(strtoupper(trans("texts.edit_details")))->asLinkTo(URL::to('/client/details'))->withAttributes(['id' => 'editDetailsButton']) !!}
                    @endif
                </div>
            </div>
        </div>

        <p>&nbsp;</p>
    </div>
@stop
