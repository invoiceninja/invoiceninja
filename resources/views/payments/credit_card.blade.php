@extends('payments.payment_method')

@section('head')
    @parent

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

@stop

@section('payment_details')

    {!! Former::vertical_open($url)
            ->autocomplete('on')
            ->addClass('payment-form')
            ->id('payment-form')
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
                'email' => 'required|email',
                'authorize_ach' => 'required',
                'tos_agree' => 'required',
                'account_number' => 'required',
                'routing_number' => 'required',
                'account_holder_name' => 'required',
                'account_holder_type' => 'required',
            )) !!}


    @if ($client)
        {{ Former::populate($client) }}
        {{ Former::populateField('first_name', $contact->first_name) }}
        {{ Former::populateField('last_name', $contact->last_name) }}
        {{ Former::populateField('email', $contact->email) }}
        @if (!$client->country_id && $client->account->country_id)
            {{ Former::populateField('country_id', $client->account->country_id) }}
        @endif
        @if (!$client->currency_id && $client->account->currency_id)
            {{ Former::populateField('currency_id', $client->account->currency_id) }}
            {{ Former::populateField('currency', $client->account->currency->code) }}
        @endif
    @endif

    @if (Utils::isNinjaDev())
        {{ Former::populateField('first_name', 'Test') }}
        {{ Former::populateField('last_name', 'Test') }}
        {{ Former::populateField('address1', '350 5th Ave') }}
        {{ Former::populateField('city', 'New York') }}
        {{ Former::populateField('state', 'NY') }}
        {{ Former::populateField('postal_code', '10118') }}
        {{ Former::populateField('country_id', 840) }}

        <script>
            $(function() {
                $('#card_number').val('4242424242424242');
                $('#cvv').val('1234');
                $('#expiration_month').val(1);
                $('#expiration_year').val({{ date_create()->modify('+3 year')->format('Y') }});
            })
        </script>
    @endif

    <h3>{{ trans('texts.contact_information') }}</h3>
    <div class="row">
        <div class="col-md-6">
            {!! Former::text('first_name')
                    ->placeholder(trans('texts.first_name'))
                    ->label('') !!}
        </div>
        <div class="col-md-6">
            {!! Former::text('last_name')
                    ->placeholder(trans('texts.last_name'))
                    ->autocomplete('family-name')
                    ->label('') !!}
        </div>
    </div>

    <div class="row" style="display:{{ isset($paymentTitle) || empty($contact->email) ? 'block' : 'none' }}">
        <div class="col-md-12">
            {!! Former::text('email')
                    ->placeholder(trans('texts.email'))
                    ->autocomplete('email')
                    ->label('') !!}
        </div>
    </div>

    <p>&nbsp;<br/>&nbsp;</p>

    @if (!empty($showAddress))
        <h3>{{ trans('texts.billing_address') }}&nbsp;<span class="help">{{ trans('texts.payment_footer1') }}</span></h3>
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
                        ->fromQuery(Cache::get('countries'), 'name', 'id')
                        ->addGroupClass('country-select')
                        ->label('') !!}
            </div>
        </div>

        <p>&nbsp;<br/>&nbsp;</p>
    @endif

    <h3>{{ trans('texts.billing_method') }}</h3>

    <div class="row">
        <div class="col-md-9">
            @if ($accountGateway->gateway_id == GATEWAY_BRAINTREE)
                <div id="card_number" class="braintree-hosted form-control"></div>
            @else
                {!! Former::text(!empty($tokenize) ? '' : 'card_number')
                        ->id('card_number')
                        ->placeholder(trans('texts.card_number'))
                        ->autocomplete('cc-number')
                        ->label('') !!}
            @endif
        </div>
        <div class="col-md-3">
            @if ($accountGateway->gateway_id == GATEWAY_BRAINTREE)
                <div id="cvv" class="braintree-hosted form-control"></div>
            @else
                {!! Former::text(!empty($tokenize) ? '' : 'cvv')
                        ->id('cvv')
                        ->placeholder(trans('texts.cvv'))
                        ->autocomplete('off')
                        ->label('') !!}
            @endif
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            @if ($accountGateway->gateway_id == GATEWAY_BRAINTREE)
                <div id="expiration_month" class="braintree-hosted form-control"></div>
            @else
                {!! Former::select(!empty($tokenize) ? '' : 'expiration_month')
                        ->id('expiration_month')
                        ->autocomplete('cc-exp-month')
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
            @endif
        </div>
        <div class="col-md-6">
            @if ($accountGateway->gateway_id == GATEWAY_BRAINTREE)
                <div id="expiration_year" class="braintree-hosted form-control"></div>
            @else
                {!! Former::select(!empty($tokenize) ? '' : 'expiration_year')
                        ->id('expiration_year')
                        ->autocomplete('cc-exp-year')
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
            @endif
        </div>
    </div>
    <div class="row" style="padding-top:18px">
        <div class="col-md-5">
            @if (isset($amount) && $client && $account->showTokenCheckbox($storageGateway/* will contain gateway id */))
                <input id="token_billing" type="checkbox" name="token_billing" {{ $account->selectTokenCheckbox() ? 'CHECKED' : '' }} value="1" style="margin-left:0px; vertical-align:top">
                <label for="token_billing" class="checkbox" style="display: inline;">{{ trans('texts.token_billing') }}</label>
                <span class="help-block" style="font-size:15px">
                    @if ($storageGateway == GATEWAY_STRIPE)
                        {!! trans('texts.token_billing_secure', ['link' => link_to('https://stripe.com/', 'Stripe.com', ['target' => '_blank'])]) !!}
                    @elseif ($storageGateway == GATEWAY_BRAINTREE)
                        {!! trans('texts.token_billing_secure', ['link' => link_to('https://www.braintreepayments.com/', 'Braintree', ['target' => '_blank'])]) !!}
                    @endif
                </span>
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

    <div class="col-md-12">
        <div id="js-error-message" style="display:none" class="alert alert-danger"></div>
    </div>

    <p>&nbsp;</p>
    <center>
        @if(isset($amount))
            {!! Button::success(strtoupper(trans('texts.pay_now') . ' - ' . $account->formatMoney($amount, $client, CURRENCY_DECORATOR_CODE)  ))
                            ->submit()
                            ->large() !!}
        @else
            {!! Button::success(strtoupper(trans('texts.add_credit_card') ))
                        ->submit()
                        ->large() !!}
        @endif
    </center>
    <p>&nbsp;</p>

    {!! Former::close() !!}

@stop
