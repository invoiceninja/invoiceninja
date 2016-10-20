@extends('public.header')

@section('content')

    @include('payments.payment_css')

    <div class="container">
        <p>&nbsp;</p>

        <div class="panel panel-default">
            <div class="panel-body">

                <div class="row">
                    <div class="col-md-7">
                        <header>
                            @if ($client && isset($invoiceNumber))
                                <h2>{{ $client->getDisplayName() }}</h2>
                                <h3>{{ trans('texts.invoice') . ' ' . $invoiceNumber }}<span>|&nbsp; {{ trans('texts.amount_due') }}: <em>{{ $account->formatMoney($amount, $client, CURRENCY_DECORATOR_CODE) }}</em></span></h3>
                            @elseif ($paymentTitle)
                                <h2>{{ $paymentTitle }}
                                    @if(isset($paymentSubtitle))
                                    <br/><small>{{ $paymentSubtitle }}</small>
                                    @endif
                                </h2>
                            @endif
                        </header>
                    </div>
                    <div class="col-md-5">
                        @if (Request::secure() || Utils::isNinjaDev())
                            <div class="secure">
                                <h3>{{ trans('texts.secure_payment') }}</h3>
                                <div>{{ trans('texts.256_encryption') }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                <p>&nbsp;<br/>&nbsp;</p>

                <div>

                    @yield('payment_details')

                </div>

            </div>

            </div>
        </div>


        <p>&nbsp;</p>
        <p>&nbsp;</p>

    </div>



    <script type="text/javascript">

        $(function() {
            $('select').change(function() {
                $(this).css({color:'#444444'});
            });

            $('#country_id').combobox();
            $('#currency_id').combobox();
            $('#first_name').focus();
        });

    </script>


@stop
