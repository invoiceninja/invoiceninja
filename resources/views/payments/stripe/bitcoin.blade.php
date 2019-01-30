@extends('payments.payment_method')

@section('head')
    @parent

    <script type="text/javascript" src="{{ asset('js/qrcode.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var qrcode = new QRCode(document.getElementById("qrcode"), {
            	text: "{{ $source['bitcoin']['uri'] }}",
                width: 330,
                height: 330,
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
            <h3>
                <a href="{{ $source['bitcoin']['uri'] }}">{{ $source['receiver']['address'] }}</a>
            </h3>
            <p>&nbsp;</p>
            {!! Button::normal(strtoupper(trans('texts.return_to_invoice')))->large()->asLinkTo($invitation->getLink()) !!}
            <p>&nbsp;</p>
        </div>
        <div class="col-md-6">
            <center>
                <div id="qrcode"></div>
            </center>
        </div>
    </div>

@stop
