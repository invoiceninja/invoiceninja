@extends('public.header')

@section('content')

<style type="text/css">
  div > label.control-label
  {
    font-weight: bold !important;    
    /* text-transform:uppercase; */
  }
</style>

{{ Former::vertical_open('payment/' . $invitationKey)->rules(array(
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
    'email' => 'required'   
)) }}

{{ Former::populate($client) }}
{{ Former::populateField('first_name', $contact->first_name) }}
{{ Former::populateField('last_name', $contact->last_name) }}


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
    <div id="secure-form" class="row">          
      <div class="col-md-7 info">
        <form>
          <div class="row">
            <div class="form-group col-md-6">
              {{ Former::text('first_name') }}
            </div>
            <div class="form-group col-md-6">
              {{ Former::text('last_name') }}
            </div>
          </div>

          <div class="row">
            <div class="form-group col-md-12">
              {{ Former::text('address1')->label('Street') }}
            </div>
          </div>

          <div class="row">
            <div class="form-group col-md-3">
              {{ Former::text('address2')->label('Apt/Suite') }}      
            </div>

            <div class="form-group col-md-3">
              {{ Former::text('city') }}          
            </div>

            <div class="form-group col-md-3">
              {{ Former::text('state')->label('State/Province') }}          
            </div>

            <div class="form-group col-md-3">
              {{ Former::text('postal_code') }}                      
            </div>
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

              <div class="col-md-6">
                <!-- <p><span class="glyphicon glyphicon-credit-card" style="margin-right: 10px;"></span><a href="#">Where Do I find CVV?</a></p> -->
              </div>
            </div>
          </div>


        </div>
      </div>
      <div class="row">
        <div class="col-md-12">
          {{ Button::block_primary_submit_lg(strtoupper(trans('texts.pay_now')) . ' - ' . Utils::formatMoney($invoice->amount, $client->currency_id) ) }}
        </div>
      </form>
    </div>
  </div>
</div>


</section>




  <!--
    </div>

    <div class="col-md-5 col-md-offset-1" style="background-color:#DDD;padding-top:16px">

      <div class="row">
        <div class="col-md-12">
          {{ Former::text('card_number') }}  
        </div>
      </div>
      

      <div class="row">
        <div class="col-md-6">
        </div>
        <div class="col-md-6">
        </div>
      </div>

      <div class="row">
        <div class="col-md-6">
          {{ Former::text('cvv') }}        
        </div>
      </div>

    </div>

    <p>&nbsp;<p/>
    <p>&nbsp;<p/>


    <div class="row">
      <div class="col-md-12">
      
        {{ Button::block_primary_submit_lg(strtoupper(trans('texts.pay_now')) . ' - ' . Utils::formatMoney($invoice->amount, $client->currency_id) ) }}

      </div>
    </div>    

  </div>
-->




{{ Former::close() }}

@stop