@extends('public.header')

@section('content')

<style type="text/css">

body {
    background-color: #f8f8f8;
    color: #1b1a1a;
}

.panel-body {
    padding-bottom: 50px;
}


.container input[type=text],
.container input[type=email],
.container select {
    font-weight: 300;
    font-family: 'Roboto', sans-serif;
    width: 100%;
    padding: 11px;
    color: #8c8c8c;
    background: #f9f9f9;
    border: 1px solid #ebe7e7;
    border-radius: 3px;
    font-size: 16px;
    min-height: 42px !important;
    font-weight: 400;
}

div.col-md-3,
div.col-md-5,
div.col-md-6,
div.col-md-7,
div.col-md-9,
div.col-md-12 {
    margin: 6px 0 6px 0;
}

span.dropdown-toggle {
    border-color: #ebe7e7;
}

.dropdown-toggle {
    margin: 0px !important;
}

.container input[placeholder],
.container select[placeholder] {
   color: #444444;
}

div.row {
    padding-top: 8px;
}

header {
    margin: 0px !important
}
    
@media screen and (min-width: 700px) {
    header {
        margin: 20px 0 75px;
        float: left;
    }

    .panel-body {
        padding-left: 150px;
        padding-right: 150px;
    }

}

h2 {
    font-weight: 300;
    font-size: 30px;
    color: #2e2b2b;
    line-height: 1;
}

h3 {
    font-weight: 900;
    margin-top: 10px;
    font-size: 15px;
}

h3 .help {
    font-style: italic;
    font-weight: normal;
    color: #888888;
}

header h3 {
    text-transform: uppercase;    
}
    
header h3 span {
    display: inline-block;
    margin-left: 8px;
}
    
header h3 em {
    font-style: normal;
    color: #eb8039;
}



.secure {
    text-align: right;
    float: right;
    background: url({{ asset('/images/icon-shield.png') }}) right 22px no-repeat;
    padding: 17px 55px 10px 0;
    }
    
.secure h3 {
    color: #36b855;
    font-size: 30px;
    margin-bottom: 8px;
    margin-top: 0px;
    }
    
.secure div {
    color: #acacac;
    font-size: 15px;
    font-weight: 900;
    text-transform: uppercase;
}



</style>

{!! Former::vertical_open($url)->rules(array(
'first_name' => 'required',
'last_name' => 'required',   
'card_number' => 'required',
'expiration_month' => 'required',
'expiration_year' => 'required',
'cvv' => 'required',
'address1' => 'required',
'city' => 'required',
'state' => 'required',
'postal_code' => 'required',
'country_id' => 'required',
'phone' => 'required',
'email' => 'required|email'
)) !!}

@if ($client)
  {{ Former::populate($client) }}
  {{ Former::populateField('first_name', $contact->first_name) }}
  {{ Former::populateField('last_name', $contact->last_name) }}
  @if (!$client->country_id && $client->account->country_id)
    {{ Former::populateField('country_id', $client->account->country_id) }} 
  @endif
@endif

<div class="container">
<p>&nbsp;</p>

<div class="panel panel-default">
  <div class="panel-body">

    <div class="row">
        <div class="col-md-7">
            <header>
                @if ($client)
                    <h2>{{ $client->getDisplayName() }}</h2>
                    <h3>{{ trans('texts.invoice') . ' ' . $invoiceNumber }}<span>|&nbsp; {{ trans('texts.amount_due') }}: <em>{{ Utils::formatMoney($amount, $currencyId) }} {{ $currencyCode }}</em></span></h3>
                @elseif ($paymentTitle)
                    <h2>{{ $paymentTitle }}<br/><small>{{ $paymentSubtitle }}</small></h2>                    
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
        <h3>{{ trans('texts.contact_information') }}</h3>
        <div class="row">
            <div class="col-md-6">
                {!! Former::text('first_name')->placeholder(trans('texts.first_name'))->label('') !!}
            </div>
            <div class="col-md-6">
                {!! Former::text('last_name')->placeholder(trans('texts.last_name'))->label('') !!}
            </div>
        </div>
        @if (isset($paymentTitle))
        <div class="row">
            <div class="col-md-12">
                {!! Former::text('email')->placeholder(trans('texts.email'))->label('') !!}
            </div>
        </div>
        @endif

        <p>&nbsp;<br/>&nbsp;</p>

        @if ($showAddress)
        <h3>{{ trans('texts.billing_address') }} &nbsp;<span class="help">{{ trans('texts.payment_footer1') }}</span></h3>
        <div class="row">
            <div class="col-md-6">
                {!! Former::text('address1')->placeholder(trans('texts.address1'))->label('') !!}
            </div>
            <div class="col-md-6">
                {!! Former::text('address2')->placeholder(trans('texts.address2'))->label('') !!}
            </div>            
        </div>
        <div class="row">
            <div class="col-md-6">
                {!! Former::text('city')->placeholder(trans('texts.city'))->label('') !!}
            </div>
            <div class="col-md-6">
                {!! Former::text('state')->placeholder(trans('texts.state'))->label('') !!}
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                {!! Former::text('postal_code')->placeholder(trans('texts.postal_code'))->label('') !!}
            </div>
            <div class="col-md-6">
                {!! Former::select('country_id')->placeholder(trans('texts.country_id'))->fromQuery($countries, 'name', 'id')->label('')->addGroupClass('country-select') !!}
            </div>
        </div>

        <p>&nbsp;<br/>&nbsp;</p>
        @endif

        <h3>{{ trans('texts.billing_method') }}</h3>
        <div class="row">
            <div class="col-md-9">
                {!! Former::text('card_number')->placeholder(trans('texts.card_number'))->label('') !!}
            </div>
            <div class="col-md-3">
                {!! Former::text('cvv')->placeholder(trans('texts.cvv'))->label('') !!}
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                {!! Former::select('expiration_month')->placeholder(trans('texts.expiration_month'))
                      ->addOption('01 - January', '1')
                      ->addOption('02 - February', '2')
                      ->addOption('03 - March', '3')
                      ->addOption('04 - April', '4')
                      ->addOption('05 - May', '5')
                      ->addOption('06 - June', '6')
                      ->addOption('07 - July', '7')
                      ->addOption('08 - August', '8')
                      ->addOption('09 - September', '9')
                      ->addOption('10 - October', '10')
                      ->addOption('11 - November', '11')
                      ->addOption('12 - December', '12')->label('')
                    !!}
            </div>
            <div class="col-md-6">
                {!! Former::select('expiration_year')->placeholder(trans('texts.expiration_year'))
                    ->addOption('2015', '2015')
                    ->addOption('2016', '2016')
                    ->addOption('2017', '2017')
                    ->addOption('2018', '2018')
                    ->addOption('2019', '2019')
                    ->addOption('2020', '2020')
                    ->addOption('2021', '2021')
                    ->addOption('2022', '2022')
                    ->addOption('2023', '2023')
                    ->addOption('2024', '2024')
                    ->addOption('2025', '2025')->label('')
                  !!}
            </div>
        </div>


        <div class="row" style="padding-top:18px">
            <div class="col-md-5">
                @if ($client && $account->showTokenCheckbox())        
                    <input id="token_billing" type="checkbox" name="token_billing" {{ $account->selectTokenCheckbox() ? 'CHECKED' : '' }} value="1" style="margin-left:0px; vertical-align:top">
                    <label for="token_billing" class="checkbox" style="display: inline;">{{ trans('texts.token_billing') }}</label>
                    <span class="help-block" style="font-size:15px">{!! trans('texts.token_billing_secure', ['stripe_link' => link_to('https://stripe.com/', 'Stripe.com', ['target' => '_blank'])]) !!}</span>
                @endif                    
            </div>  

            <div class="col-md-7">            
            @if (isset($acceptedCreditCardTypes))                
                <div class="pull-right">
                    @foreach ($acceptedCreditCardTypes as $card)
                    <img src="{{ $card['source'] }}" alt="{{ $card['alt'] }}" style="width: 70px; display: inline; margin-right: 6px;"/>
                    @endforeach
                </div>
            @endif
            </div>
        </div>
        

        <p>&nbsp;<br/>&nbsp;</p>

        <div class="row">
            <div class="col-md-4 col-md-offset-4">
                {!! Button::success(strtoupper(trans('texts.pay_now') . ' - ' . Utils::formatMoney($amount, $currencyId) ))->submit()->block()->large() !!}
            </div>
        </div>


    </div>

  </div>
</div>    
    

<p>&nbsp;</p>
<p>&nbsp;</p>

</div>

<!--
    @if (isset($paymentTitle))
      <h2>{{ $paymentTitle }}<br/>
      @if (isset($paymentSubtitle))
        <small>{{ $paymentSubtitle }}</small>
      @endif    
      </h2>&nbsp;<p/>
    @endif
-->

{!! Former::close() !!}

<script type="text/javascript">
    
    $(function() {
        $('select').change(function() {
            $(this).css({color:'#444444'});
        });

        $('#country_id').combobox();
    });

</script>

@stop