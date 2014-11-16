@extends('public.header')

@section('content')

<style type="text/css">
  div > label.control-label
  {
    font-weight: bold !important;    
    /* text-transform:uppercase; */
  }
  .alignCenterText{
	text-align: center;
	font-weight: bold;
	font-size: 20px;
	margin-bottom: 5%;
	}
	.boldText{
		font-weight: bold;
	}	

</style>

{{ Former::vertical_open($url)->rules(array(
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
'country' => 'required',
'phone' => 'required',
'email' => 'required|email'
)) }}

@if ($client)
  {{ Former::populate($client) }}
  {{ Former::populateField('first_name', $contact->first_name) }}
  {{ Former::populateField('last_name', $contact->last_name) }}
@endif

<section class="hero background hero-secure center" data-speed="2" data-type="background">
  <div class="container">
    <div class="row">
      <h1>Secure Payment</h1>
      <p class="thin"><img src="{{ asset('images/icon-secure-pay.png') }}">256-BiT Encryption</p>
      <!-- <img src="{{ asset('images/providers.png') }}"> -->
    </div>
  </div>
</section>

<section class="secure">
  <div class="container">
    @if (isset($paymentTitle))
      <h2>{{ $paymentTitle }}<br/>
      @if (isset($paymentSubtitle))
        <small>{{ $paymentSubtitle }}</small>
      @endif    
      </h2>&nbsp;<p/>
    @endif
    
    <div id="secure-form" class="row">          
      
      <div class="col-md-7 info">
      	<div class="col-md-12 alignCenterText" >
     	  {{ otrans('texts.payment_title') }}
        </div>
      	
        <div class="row">           
          @if (isset($paymentTitle))
            <div class="form-group col-md-4">
              {{ Former::text('first_name') }}
            </div>
            <div class="form-group col-md-4">
              {{ Former::text('last_name') }}
            </div>          
            <div class="form-group col-md-4">
              {{ Former::text('email') }}
            </div>          
          @else
            <div class="form-group col-md-6">
              {{ Former::text('first_name') }}
            </div>
            <div class="form-group col-md-6">
              {{ Former::text('last_name') }}
            </div>            
          @endif
        </div>

        <div class="row">
          <div class="form-group col-md-8">
            {{ Former::text('address1')->label('Street') }}
          </div>
          <div class="form-group col-md-4">
            {{ Former::text('address2')->label('Apt/Suite') }}      
          </div>
        </div>

        <div class="row">
          <div class="form-group col-md-4">
            {{ Former::text('city') }}          
          </div>
          <div class="form-group col-md-4">
            {{ Former::text('state')->label('State/Province') }}          
          </div>
          <div class="form-group col-md-4">
            {{ Former::text('postal_code') }}                      
          </div>
        </div>
        
        <div class="row">
          <h5 class="col-md-12 boldText" >
              {{ otrans('texts.payment_footer1') }}
          </h5>
          <h5 class="col-md-12 boldText">
              {{ otrans('texts.payment_footer2') }}   
          </h5>
        </div>
        
      </div>


      @if(strtolower($gateway->name) == 'beanstream')
      <div class="row">
        <div class="form-group col-md-4">
          {{ Former::text('phone') }}
        </div>
        <div class="form-group col-md-4">
          {{ Former::text('email') }}
        </div>
        <div class="form-group col-md-4">
          {{ Former::select('country')->addOption('','')->label('Country')
          ->fromQuery($countries, 'name', 'iso_3166_2') }}        
        </div>
      </div>                
      @endif


      <div class="col-md-5">
      	<div class="col-md-12 alignCenterText" >
     	  &nbsp;<!--{{ trans('texts.balance_due') . ' ' . Utils::formatMoney($amount, $currencyId) }}-->
        </div>
        <div class="col-md-12"> 
        <div class="card">
          <div class="row">
            <div class="form-group col-md-12">
              {{ Former::text('card_number') }}  
              <!-- <span class="glyphicon glyphicon-lock"></span> -->
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-6">
              {{ Former::select('expiration_month')->addOption('','')
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
              ->addOption('12 - December', '12')
            }}

          </div>
          <div class="form-group col-md-6">
            {{ Former::select('expiration_year')->addOption('','')
            ->addOption('2014', '2014')
            ->addOption('2015', '2015')
            ->addOption('2016', '2016')
            ->addOption('2017', '2017')
            ->addOption('2018', '2018')
            ->addOption('2019', '2019')
            ->addOption('2020', '2020')
          }}          

        </div>
      </div>


      <div class="row">
	        <div class="form-group col-md-6">
	          {{ Former::text('cvv') }}                        
	        </div>
			<div>
	               <h5 class="boldText" style="margin-top: 8%;margin-left: 5%;">{{ otrans('texts.payment_cvv') }}</h5>                
	        </div>
        <div class="col-md-6">
          <!-- <p><span class="glyphicon glyphicon-credit-card" style="margin-right: 10px;"></span><a href="#">Where Do I find CVV?</a></p> -->
        </div>
      </div>

      @if(isset($acceptedCreditCardTypes))
        <div class="row">
          <div class="form-group col-md-12">
            @foreach ($acceptedCreditCardTypes as $card)
              <img src="{{ $card['source'] }}" alt="{{ $card['alt'] }}" style="width: 70px; display: inline; margin-right: 6px;"/>
            @endforeach
          </div>
        </div>
      @endif

    </div>

	</div>
  </div>
</div>
<div class="row">
  <div class="col-md-4 col-md-offset-4">
    {{ Button::block_primary_submit_lg(strtoupper(trans('texts.pay_now')) . ' - ' . Utils::formatMoney($amount, $currencyId) ) }}
  </div>
</div>
<div class="row">
  <div class="col-md-12">
    <p>&nbsp;</p>
    <a href="https://www.invoiceninja.com/terms" target="_blank">Click here</a> to view our terms of service.
  </div>
</div>

</div>
</div>


</section>



{{ Former::close() }}

@stop