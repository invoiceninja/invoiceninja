@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <link href="{{ asset('css/card-js.min.css') }}" rel="stylesheet" type="text/css">
@endsection

@section('gateway_content')

        <form style="display:none" target="hss_iframe" name="form_iframe"
        method="post"
        action="https://securepayments.paypal.com/webapps/HostedSoleSolutionApp/
        webflow/sparta/hostedSoleSolutionProcess">
        <input type="hidden" name="cmd" value="_hosted-payment">
        <input type="hidden" name="subtotal" value="{{ $total['amount_with_fee'] }}">
        <input type="hidden" name="business" value="{{ $gateway->company_gateway->getConfigField('username') }}">
        <input type="hidden" name="paymentaction" value="sale">
        <input type="hidden" name="template" value="templateA">
        <input type="hidden" name="return"
        value="https://yourwebsite.com/receipt_page.html">
        </form>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
       <iframe name="hss_iframe" width="570px" height="540px"></iframe>

        <form style="display:none" target="hss_iframe" name="form_iframe" id="form_iframe"
        method="post"
        action="https://pilot-payflowpro.paypal.com/">
        <input type="hidden" name="cmd" value="_hosted-payment">
        <input type="hidden" name="subtotal" value="{{ $total['amount_with_fee'] }}">
        <input type="hidden" name="business" value="{{ $gateway->company_gateway->getConfigField('username') }}">
        <input type="hidden" name="paymentaction" value="sale">
        <input type="hidden" name="template" value="templateA">
        <input type="hidden" name="return"
        value="https://yourwebsite.com/receipt_page.html">
        </form>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    @include('portal.ninja2020.gateways.includes.pay_now')
@endsection

@section('gateway_footer')
@endsection

@push('footer')
<script type="text/javascript">
 document.getElementById('form_iframe').submit();
</script>
@endpush