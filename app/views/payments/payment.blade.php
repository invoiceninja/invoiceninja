@extends('header')

@section('content')

  <style type="text/css">
  div > label.control-label
  {
    font-weight: normal !important;    
  }d
  </style>

  {{ Former::open('payment/' . $invitationKey)->rules(array(
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
  

  <div class="row">
    <div class="col-md-6 col-md-offset-2">

      {{ Former::legend('secure_payment') }}
      {{ Former::text('first_name') }}
      {{ Former::text('last_name') }}

      <p>&nbsp;<p/>
      
      {{ Former::text('card_number') }}
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
      {{ Former::select('expiration_year')->addOption('','')
            ->addOption('2014', '2014')
            ->addOption('2015', '2015')
            ->addOption('2016', '2016')
            ->addOption('2017', '2017')
            ->addOption('2018', '2018')
            ->addOption('2019', '2019')
            ->addOption('2020', '2020')
      }}

      {{ Former::text('cvv') }}

      <p>&nbsp;<p/>

      {{ Former::text('address1')->label('Street') }}
      {{ Former::text('address2')->label('Apt/Suite') }}
      {{ Former::text('city') }}
      {{ Former::text('state')->label('State/Province') }}
      {{ Former::text('postal_code') }}
      
      <?php if(strtolower($gateway->name) == 'beanstream') { ?>
		{{ Former::select('country')->addOption('','')->label('Country')
			->fromQuery($countries, 'name', 'iso_3166_2') }}
	  	{{ Former::text('phone') }}
	  	{{ Former::text('email') }}
	  <?php } ?>
	  
	  <?php echo($gateway->name); ?>
      {{ Former::actions( Button::primary_submit_lg(trans('texts.pay_now') . ' - ' . Utils::formatMoney($invoice->amount, $client->currency_id) )) }}

    </div>
  </div>    


  {{ Former::close() }}

@stop