@extends('public.header')

@section('head')
    @parent

    <style type="text/css">
        body {
            line-height: 1.5em;
        }

        div.main-container {
            min-height: 700px;
        }

        div.row {
            padding-top: 2em;
            padding-bottom: 2em;
        }

        div.logo img {
            max-width:300px;
            max-height:200px;
        }

        div.address-details {
            color: #666666;
            font-size: 15px;
            line-height: 1.8em;
        }

        div.col-md-4-left {
            padding-left: 15px;
            padding-right: 6px;
        }
        div.col-md-4-center {
            padding-left: 6px;
            padding-right: 6px;
        }
        div.col-md-4-right {
            padding-left: 6px;
            padding-right: 15px;
        }

        div.well {
            background-color: white;
            color: #0b4d78;
            text-transform: uppercase;
            text-align: center;
            font-weight: 600;
            padding-top: 40px;
            padding-bottom: 40px;
        }

        div.well .fa {
            color: green;
            font-size: 18px;
            margin-bottom: 6px;
        }

        div.well .amount {
            margin-top: 10px;
            font-size: 32px;
            font-weight: 300;
            color: black;
        }

        table.dataTable thead > tr > th, table.invoice-table thead > tr > th {
            background-color: {{ $color }} !important;
        }

        .pagination>.active>a,
        .pagination>.active>span,
        .pagination>.active>a:hover,
        .pagination>.active>span:hover,
        .pagination>.active>a:focus,
        .pagination>.active>span:focus {
            background-color: {{ $color }};
            border-color: {{ $color }};
        }

        table.table thead .sorting:after { content: '' !important }
        table.table thead .sorting_asc:after { content: '' !important }
        table.table thead .sorting_desc:after { content: '' !important }
        table.table thead .sorting_asc_disabled:after { content: '' !important }
        table.table thead .sorting_desc_disabled:after { content: '' !important }

    </style>

@stop

@section('content')

    <div class="container main-container">

        <div class="row">
            <div class="col-md-6 logo">
                @if ($account->hasLogo())
                    {!! HTML::image($account->getLogoURL()) !!}
                @else
                    <h2>{{ $account->name}}</h2>
                @endif
            </div>
            <div class="col-md-3 address-details">
                @if ($account->address1)
                    {{ $account->address1 }}<br/>
                @endif
                @if ($account->address2)
                    {{ $account->address2 }}<br/>
                @endif
                @if ($account->getCityState())
                    {{ $account->getCityState() }}<br/>
                @endif
                @if ($account->country)
                    {{ $account->country->name }}
                @endif
            </div>
            <div class="col-md-3 address-details">
                @if ($account->website)
                    <i class="fa fa-globe" style="width: 20px"></i><a href="{{ Utils::addHttp($account->website) }}" target="_blank">{{ $account->website }}</a><br/>
                @endif
                @if ($account->work_phone)
                    <i class="fa fa-phone" style="width: 20px"></i>{{ $account->work_phone }}<br/>
                @endif
                @if ($account->work_email)
                    <i class="fa fa-envelope" style="width: 20px"></i>{!! HTML::mailto($account->work_email, $account->work_email) !!}<br/>
                @endif
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 col-md-4-left">
                <div class="well">
                    <div class="fa fa-file-text-o"></div>
                    <div>
                        {{ trans('texts.total_invoiced') }}
                    </div>
                    <div class="amount">
                        {{ Utils::formatMoney($client->paid_to_date + $client->balance, $client->currency_id ?: $account->currency_id) }}
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-md-4-center">
                <div class="well">
                    <div class="fa fa-credit-card"></div>
                    <div>
                        {{ trans('texts.paid_to_date') }}
                    </div>
                    <div class="amount">
                        {{ Utils::formatMoney($client->paid_to_date, $client->currency_id ?: $account->currency_id) }}
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-md-4-right">
                <div class="well">
                    <div class="fa fa-server"></div>
                    <div>
                        {{ trans('texts.open_balance') }}
                    </div>
                    <div class="amount">
                        {{ Utils::formatMoney($client->balance, $client->currency_id ?: $account->currency_id) }}
                    </div>
                </div>
            </div>
        </div>

        @if (!empty($account->getTokenGatewayId()))
                <div class="row">
                    <div class="col-xs-12">
                    @include('payments.paymentmethods_list')
                </div>
        </div>
        @endif

        <div style="min-height: 550px">
            {!! Datatable::table()
                ->addColumn(
                    trans('texts.date'),
                    trans('texts.message'),
                    trans('texts.balance'),
                    trans('texts.adjustment'))
                ->setUrl(route('api.client.activity'))
                ->setOptions('bFilter', false)
                ->setOptions('aaSorting', [['0', 'desc']])
                ->setOptions('sPaginationType', 'bootstrap')
                ->render('datatable') !!}
        </div>

        <p>&nbsp;</p>

    </div>

@stop
