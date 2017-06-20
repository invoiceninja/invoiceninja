@extends('payments.payment_method')

@section('head')
    @parent

    <script src="{{ asset('js/card.min.js') }}"></script>

    <style type="text/css">
        div.jp-card-container {
            transform: scale(.89) !important;
            transform-origin: right top;
        }
    </style>

    <script type="text/javascript">

        $(function() {
            $('.payment-form').submit(function(event) {
                var $form = $(this);

                // Disable the submit button to prevent repeated clicks
                $form.find('button').prop('disabled', true);

                return true;
            });

            @if ($accountGateway->gateway_id != GATEWAY_BRAINTREE)
                var card = new Card({
                    form: 'form#payment-form', // *required*
                    container: '.card-wrapper', // *required*

                    formSelectors: {
                        numberInput: 'input#card_number', // optional — default input[name="number"]
                        expiryInput: 'input#expiry', // optional — default input[name="expiry"]
                        cvcInput: 'input#cvv', // optional — default input[name="cvc"]
                        nameInput: 'input#first_name, input#last_name'
                    },

                    //width: 100, // optional — default 350px
                    formatting: true, // optional - default true

                    // Strings for translation - optional
                    messages: {
                        monthYear: "{{ trans('texts.month_year') }}",
                        validDate: "{{ trans('texts.valid_thru') }}",
                    },

                    // Default placeholders for rendered fields - optional
                    placeholders: {
                        number: '•••• •••• •••• ••••',
                        name: "{{ $client ? ($contact->first_name . ' ' . $contact->last_name) : trans('texts.full_name') }}",
                        expiry: '••/••',
                        cvc: '•••'
                    },

                    masks: {
                        cardNumber: '•' // optional - mask card number
                    },
                    debug: true,
                });

            @endif
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
                        ->fromQuery($countries, 'name', 'id')
                        ->addGroupClass('country-select')
                        ->label('') !!}
            </div>
        </div>

        <p>&nbsp;<br/>&nbsp;</p>
    @endif

    @if ($accountGateway->isGateway(GATEWAY_WEPAY) && $account->token_billing_type_id == TOKEN_BILLING_DISABLED)
        {{--- do nothing ---}}
    @else
        <div class="row">
            <div class="col-lg-{{ ($accountGateway->gateway_id == GATEWAY_BRAINTREE) ? 12 : 8 }}">

                <h3>
                    {{ trans('texts.billing_method') }}
                    @if (isset($acceptedCreditCardTypes))
                        &nbsp;
                        @foreach ($acceptedCreditCardTypes as $card)
                            <img src="{{ $card['source'] }}" alt="{{ $card['alt'] }}" style="width: 34px; display: inline; margin-left: 7px;"/>
                        @endforeach
                    @endif
                    <br/>
                </h3>

                <div class="row">
                    <div class="col-md-12">
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
                </div>
                <div class="row">
                    <div class="col-md-5">
                        @if ($accountGateway->gateway_id == GATEWAY_BRAINTREE)
                            <div id="expiration_month" class="braintree-hosted form-control"></div>
                        @else
                            {!! Former::select(!empty($tokenize) ? '' : 'expiration_month')
                                    ->id('expiration_month')
                                    ->autocomplete('cc-exp-month')
                                    ->placeholder(trans('texts.expiration_month'))
                                      ->addOption('01 - ' . trans('texts.january'), '1')
                                      ->addOption('02 - ' . trans('texts.february'), '2')
                                      ->addOption('03 - ' . trans('texts.march'), '3')
                                      ->addOption('04 - ' . trans('texts.april'), '4')
                                      ->addOption('05 - ' . trans('texts.may'), '5')
                                      ->addOption('06 - ' . trans('texts.june'), '6')
                                      ->addOption('07 - ' . trans('texts.july'), '7')
                                      ->addOption('08 - ' . trans('texts.august'), '8')
                                      ->addOption('09 - ' . trans('texts.september'), '9')
                                      ->addOption('10 - ' . trans('texts.october'), '10')
                                      ->addOption('11 - ' . trans('texts.november'), '11')
                                      ->addOption('12 - ' . trans('texts.december'), '12')->label('')
                                    !!}
                        @endif
                    </div>
                    <div class="col-md-4">
                        @if ($accountGateway->gateway_id == GATEWAY_BRAINTREE)
                            <div id="expiration_year" class="braintree-hosted form-control"></div>
                        @else
                            {!! Former::select(!empty($tokenize) ? '' : 'expiration_year')
                                    ->id('expiration_year')
                                    ->autocomplete('cc-exp-year')
                                    ->placeholder(trans('texts.expiration_year'))
                                    ->options(
                                        array_combine(
                                            range(date('Y'), date('Y') + 10),
                                            range(date('Y'), date('Y') + 10)
                                        )
                                    )
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

                <div class="row" style="padding-top:18px">

                    <div class="col-md-12">
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
                </div>
            </div>
            <div class="col-lg-4">
                <div class='card-wrapper'></div>
            </div>
        </div>
    @endif

    <div class="col-md-12">
        <div id="js-error-message" style="display:none" class="alert alert-danger"></div>
    </div>

    <p>&nbsp;</p>
    <center>
        @if (isset($invitation))
            {!! Button::normal(strtoupper(trans('texts.cancel')))->large()->asLinkTo($invitation->getLink()) !!}
            &nbsp;&nbsp;
        @endif
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
