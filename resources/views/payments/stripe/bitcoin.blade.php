@extends('payments.payment_method')

@section('head')
    @parent

    <script type="text/javascript" src="{{ asset('js/qrcode.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var qrcode = new QRCode(document.getElementById("qrcode"), {
            	text: "{{ $source['bitcoin']['uri'] }}",
                width: 300,
                height: 300,
            });
        });
    </script>

@stop


@section('payment_details')

    <div class="row">
        <div class="col-md-6">
            <img src="{{ asset('/images/gateways/logo_Bitcoin.png') }}"/>
            <p>&nbsp;</p>
            <h2>{{ $source['bitcoin']['amount'] / 100000000 }} BTC</h2>
            <h3>{{ $source['receiver']['address'] }}</h3>
            <p>&nbsp;</p>
            {!! Button::normal(strtoupper(trans('texts.cancel')))->large()->asLinkTo($invitation->getLink()) !!}
        </div>
        <div class="col-md-6">
            <center>
                <div id="qrcode"></div>
            </center>
        </div>
    </div>

@stop
