@extends('public.header')

@section('head')
    @parent

    @if ($accountGateway->getPublishableStripeKey())
        <script type="text/javascript" src="https://js.stripe.com/v2/"></script>
        <script type="text/javascript">
            Stripe.setPublishableKey('{{ $accountGateway->getPublishableStripeKey() }}');
            $(function() {
              $('.payment-form').submit(function(event) {
                var $form = $(this);

                // Disable the submit button to prevent repeated clicks
                $form.find('button').prop('disabled', true);

                Stripe.card.createToken($form, stripeResponseHandler);

                // Prevent the form from submitting with the default action
                return false;
              });
            });

            function stripeResponseHandler(status, response) {
                var $form = $('.payment-form');

                if (response.error) {
                    // Show the errors on the form
                    var error = response.error.message;
                    $form.find('button').prop('disabled', false);
                    $('#js-error-message').html(error).fadeIn();
                } else {
                    // response contains id and card, which contains additional card details
                    var token = response.id;
                    // Insert the token into the form so it gets submitted to the server
                    $form.append($('<input type="hidden" name="stripeToken"/>').val(token));
                    // and submit
                    $form.get(0).submit();
                }
            };
        </script>
    @else
        <script type="text/javascript">
            $(function() {
              $('.payment-form').submit(function(event) {
                var $form = $(this);

                // Disable the submit button to prevent repeated clicks
                $form.find('button').prop('disabled', true);

                return true;
              });
            });
        </script>
    @endif
    
@stop

@section('content')

@include('payments.payment_css')

{!! Former::vertical_open($url)
        ->autocomplete('on')
        ->addClass('payment-form')
        ->rules(array(
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

<div id="js-error-message" style="display:none" class="alert alert-danger"></div>

<div class="panel panel-default">
  <div class="panel-body">

    <div class="row">
        <div class="col-md-7">
            <header>
                @if ($client)
                    <h2>{{ $client->getDisplayName() }}</h2>
                    <h3>{{ trans('texts.invoice') . ' ' . $invoiceNumber }}<span>|&nbsp; {{ trans('texts.amount_due') }}: <em>{{ $account->formatMoney($amount, $client, true) }}</em></span></h3>
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
                {!! Former::text('first_name')
                        ->placeholder(trans('texts.first_name'))
                        ->autocomplete('given-name')
                        ->label('') !!}
            </div>
            <div class="col-md-6">
                {!! Former::text('last_name')
                        ->placeholder(trans('texts.last_name'))
                        ->autocomplete('family-name')
                        ->label('') !!}
            </div>
        </div>
        @if (isset($paymentTitle))
        <div class="row">
            <div class="col-md-12">
                {!! Former::text('email')
                        ->placeholder(trans('texts.email'))
                        ->autocomplete('email')
                        ->label('') !!}
            </div>
        </div>
        @endif

        <p>&nbsp;<br/>&nbsp;</p>

        @if ($showAddress)
        <h3>{{ trans('texts.billing_address') }} &nbsp;<span class="help">{{ trans('texts.payment_footer1') }}</span></h3>
        <div class="row">
            <div class="col-md-6">
                {!! Former::text('address1')
                        ->autocomplete('address-line1')
                        ->placeholder(trans('texts.address1'))
                        ->label('') !!}
            </div>
            <div class="col-md-6">
                {!! Former::text('address2')
                        ->autocomplete('address-line2')
                        ->placeholder(trans('texts.address2'))
                        ->label('') !!}
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                {!! Former::text('city')
                        ->autocomplete('address-level2')
                        ->placeholder(trans('texts.city'))
                        ->label('') !!}
            </div>
            <div class="col-md-6">
                {!! Former::text('state')
                        ->autocomplete('address-level1')
                        ->placeholder(trans('texts.state'))
                        ->label('') !!}
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                {!! Former::text('postal_code')
                        ->autocomplete('postal-code')
                        ->placeholder(trans('texts.postal_code'))
                        ->label('') !!}
            </div>
            <div class="col-md-6">
                {!! Former::select('country_id')
                        ->placeholder(trans('texts.country_id'))
                        ->fromQuery($countries, 'name', 'id')
                        ->addGroupClass('country-select')
                        ->label('') !!}
            </div>
        </div>

        <p>&nbsp;<br/>&nbsp;</p>
        @endif

        <h3>{{ trans('texts.billing_method') }}</h3>
        <div class="row">
            <div class="col-md-9">
                {!! Former::text($accountGateway->getPublishableStripeKey() ? '' : 'card_number')
                        ->placeholder(trans('texts.card_number'))
                        ->autocomplete('cc-number')
                        ->data_stripe('number')
                        ->label('') !!}
            </div>
            <div class="col-md-3">
                {!! Former::text($accountGateway->getPublishableStripeKey() ? '' : 'cvv')
                        ->placeholder(trans('texts.cvv'))
                        ->autocomplete('off')
                        ->data_stripe('cvc')
                        ->label('') !!}
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                {!! Former::select($accountGateway->getPublishableStripeKey() ? '' : 'expiration_month')
                        ->autocomplete('cc-exp-month')
                        ->data_stripe('exp-month')
                        ->placeholder(trans('texts.expiration_month'))
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
                {!! Former::select($accountGateway->getPublishableStripeKey() ? '' : 'expiration_year')
                        ->autocomplete('cc-exp-year')
                        ->data_stripe('exp-year')
                        ->placeholder(trans('texts.expiration_year'))
                            ->addOption('2016', '2016')
                            ->addOption('2017', '2017')
                            ->addOption('2018', '2018')
                            ->addOption('2019', '2019')
                            ->addOption('2020', '2020')
                            ->addOption('2021', '2021')
                            ->addOption('2022', '2022')
                            ->addOption('2023', '2023')
                            ->addOption('2024', '2024')
                            ->addOption('2025', '2025')
                            ->addOption('2026', '2026')->label('')
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

        <center>
            {!! Button::success(strtoupper(trans('texts.pay_now') . ' - ' . $account->formatMoney($amount, $client, true)  ))
                            ->submit()
                            ->large() !!}
        </center>

    </div>

  </div>
</div>    
    

<p>&nbsp;</p>
<p>&nbsp;</p>

</div>

{!! Former::close() !!}

<script type="text/javascript">
    
    $(function() {
        $('select').change(function() {
            $(this).css({color:'#444444'});
        });

        $('#country_id').combobox();
        $('#first_name').focus();
    });

</script>

@stop