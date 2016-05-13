@extends('public.header')

@section('head')
    @parent
    @if (!empty($braintreeClientToken))
        @include('payments.tokenization_braintree')
    @elseif (isset($accountGateway) && $accountGateway->getPublishableStripeKey())
        @include('payments.tokenization_stripe')
    @elseif (isset($accountGateway) && $accountGateway->gateway_id == GATEWAY_WEPAY)
        @include('payments.tokenization_wepay')
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


    @if($paymentType == PAYMENT_TYPE_STRIPE_ACH)
        {!! Former::open($url)
            ->autocomplete('on')
            ->addClass('payment-form')
            ->id('payment-form')
            ->rules(array(
                'first_name' => 'required',
                'last_name' => 'required',
                'account_number' => 'required',
                'routing_number' => 'required',
                'account_holder_name' => 'required',
                'account_holder_type' => 'required',
                'authorize_ach' => 'required',
            )) !!}
    @else
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
                    'email' => 'required|email'
                )) !!}
    @endif

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
    @endif


    <div class="container">
        <p>&nbsp;</p>

        <div class="panel panel-default">
            <div class="panel-body">

                <div class="row">
                    <div class="col-md-7">
                        <header>
                            @if ($client && isset($invoiceNumber))
                                <h2>{{ $client->getDisplayName() }}</h2>
                                <h3>{{ trans('texts.invoice') . ' ' . $invoiceNumber }}<span>|&nbsp; {{ trans('texts.amount_due') }}: <em>{{ $account->formatMoney($amount, $client, true) }}</em></span></h3>
                            @elseif ($paymentTitle)
                                <h2>{{ $paymentTitle }}
                                    @if(isset($paymentSubtitle))
                                    <br/><small>{{ $paymentSubtitle }}</small>
                                    @endif
                                </h2>
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
                    <div id="paypal-container"></div>
                    @if($paymentType != PAYMENT_TYPE_STRIPE_ACH && $paymentType != PAYMENT_TYPE_BRAINTREE_PAYPAL)
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
                        <div class="row" style="display:{{ isset($paymentTitle) ? 'block' : 'none' }}">
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

                        <h3>{{ trans('texts.billing_method') }}</h3>
                    @endif


                    @if($paymentType == PAYMENT_TYPE_STRIPE_ACH)
                        @if($accountGateway->getPlaidEnabled())
                            <div id="plaid_container">
                                <a class="btn btn-default btn-lg" id="plaid_link_button">
                                    <img src="{{ URL::to('images/plaid-logo.svg') }}">
                                    <img src="{{ URL::to('images/plaid-logowhite.svg') }}" class="hoverimg">
                                    {{ trans('texts.link_with_plaid') }}
                                </a>
                                <div id="plaid_linked">
                                    <div id="plaid_linked_status"></div>
                                    <a href="#" id="plaid_unlink">{{ trans('texts.unlink') }}</a>
                                </div>
                            </div>
                        @endif
                        <div id="manual_container">
                            @if($accountGateway->getPlaidEnabled())
                            <div id="plaid_or"><span>{{ trans('texts.or') }}</span></div>
                            <h4>{{ trans('texts.link_manually') }}</h4>
                            @endif
                            <p>{{ trans('texts.ach_verification_delay_help') }}</p>
                            {!! Former::radios('account_holder_type')->radios(array(
                                    trans('texts.individual_account') => array('value' => 'individual'),
                                    trans('texts.company_account') => array('value' => 'company'),
                                ))->inline()->label(trans('texts.account_holder_type'));  !!}
                            {!! Former::text('account_holder_name')
                                   ->label(trans('texts.account_holder_name')) !!}
                                    {!! Former::select('country_id')
                                            ->label(trans('texts.country_id'))
                                            ->fromQuery($countries, 'name', 'id')
                                            ->addGroupClass('country-select') !!}
                                    {!! Former::select('currency')
                                            ->label(trans('texts.currency_id'))
                                            ->fromQuery($currencies, 'name', 'code')
                                            ->addGroupClass('currency-select') !!}
                                    {!! Former::text('')
                                            ->id('routing_number')
                                            ->label(trans('texts.routing_number')) !!}
                                    <div class="form-group" style="margin-top:-15px">
                                        <div class="col-md-8 col-md-offset-4">
                                            <div id="bank_name"></div>
                                        </div>
                                    </div>
                                    {!! Former::text('')
                                            ->id('account_number')
                                            ->label(trans('texts.account_number')) !!}
                                    {!! Former::text('')
                                            ->id('confirm_account_number')
                                            ->label(trans('texts.confirm_account_number')) !!}
                                    {!! Former::checkbox('authorize_ach')
                                            ->text(trans('texts.ach_authorization', ['company'=>$account->getDisplayName()]))
                                            ->label(' ') !!}
                            </div>
                        </div>
                        <div class="col-md-8 col-md-offset-4">
                            {!! Button::success(strtoupper(trans('texts.add_account')))
                                            ->submit()
                                            ->withAttributes(['id'=>'add_account_button'])
                                            ->large() !!}
                            @if($accountGateway->getPlaidEnabled() && !empty($amount))
                                {!! Button::success(strtoupper(trans('texts.pay_now') . ' - ' . $account->formatMoney($amount, $client, true)  ))
                                            ->submit()
                                            ->withAttributes(['style'=>'display:none', 'id'=>'pay_now_button'])
                                            ->large() !!}
                            @endif
                        </div>
                    @elseif($paymentType == PAYMENT_TYPE_BRAINTREE_PAYPAL)
                        <h3>{{ trans('texts.paypal') }}</h3>
                        <div>{{$paypalDetails->firstName}} {{$paypalDetails->lastName}}</div>
                        <div>{{$paypalDetails->email}}</div>
                        <input type="hidden" name="sourceToken" value="{{$sourceId}}">
                        <input type="hidden" name="first_name" value="{{$paypalDetails->firstName}}">
                        <input type="hidden" name="last_name" value="{{$paypalDetails->lastName}}">
                        <input type="hidden" name="email" value="{{$paypalDetails->email}}">
                        <p>&nbsp;</p>
                        @if (isset($amount) && $client && $account->showTokenCheckbox())
                            <input id="token_billing" type="checkbox" name="token_billing" {{ $account->selectTokenCheckbox() ? 'CHECKED' : '' }} value="1" style="margin-left:0px; vertical-align:top">
                            <label for="token_billing" class="checkbox" style="display: inline;">{{ trans('texts.token_billing_braintree_paypal') }}</label>
                            <span class="help-block" style="font-size:15px">
                                            {!! trans('texts.token_billing_secure', ['link' => link_to('https://www.braintreepayments.com/', 'Braintree', ['target' => '_blank'])]) !!}
                            </span>
                        @endif
                        <p>&nbsp;</p>
                        <center>
                            @if(isset($amount))
                                {!! Button::success(strtoupper(trans('texts.pay_now') . ' - ' . $account->formatMoney($amount, $client, true)  ))
                                                ->submit()
                                                ->large() !!}
                            @else
                                {!! Button::success(strtoupper(trans('texts.add_credit_card') ))
                                            ->submit()
                                            ->large() !!}
                            @endif
                        </center>
                    @else
                        <div class="row">
                            <div class="col-md-9">
                                @if (!empty($braintreeClientToken))
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
                                @if (!empty($braintreeClientToken))
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
                                @if (!empty($braintreeClientToken))
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
                                @if (!empty($braintreeClientToken))
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

                        <p>&nbsp;</p>
                        <center>
                            @if(isset($amount))
                            {!! Button::success(strtoupper(trans('texts.pay_now') . ' - ' . $account->formatMoney($amount, $client, true)  ))
                                            ->submit()
                                            ->large() !!}
                            @else
                            {!! Button::success(strtoupper(trans('texts.add_credit_card') ))
                                        ->submit()
                                        ->large() !!}
                            @endif
                        </center>
                        <p>&nbsp;</p>
                    @endif

                    <div id="js-error-message" style="display:none" class="alert alert-danger"></div>
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
            $('#country').combobox();
            $('#currency').combobox();
            $('#first_name').focus();

            @if($paymentType == PAYMENT_TYPE_STRIPE_ACH)
            var routingNumberCache = {};
            $('#routing_number, #country').on('change keypress keyup keydown paste', function(){setTimeout(function () {
                var routingNumber = $('#routing_number').val().replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, '');

                if (routingNumber.length != 9 || $("#country").val() != 'US' || routingNumberCache[routingNumber] === false) {
                    $('#bank_name').hide();
                } else if (routingNumberCache[routingNumber]) {
                    $('#bank_name').empty().append(routingNumberCache[routingNumber]).show();
                } else {
                    routingNumberCache[routingNumber] = false;
                    $('#bank_name').hide();
                    $.ajax({
                        url:"{{ URL::to('/bank') }}/" + routingNumber,
                        success:function(data) {
                            var els = $().add(document.createTextNode(data.name + ", " + data.city + ", " + data.state));
                            routingNumberCache[routingNumber] = els;

                            // Still the same number?
                            if (routingNumber == $('#routing_number').val().replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, '')) {
                                $('#bank_name').empty().append(els).show();
                            }
                        },
                        error:function(xhr) {
                            if (xhr.status == 404) {
                                var els = $(document.createTextNode('{{trans('texts.unknown_bank')}}'));
                                ;
                                routingNumberCache[routingNumber] = els;

                                // Still the same number?
                                if (routingNumber == $('#routing_number').val().replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, '')) {
                                    $('#bank_name').empty().append(els).show();
                                }
                            }
                        }
                    })
                }
            },10)})
            @endif
        });

    </script>
    @if (isset($accountGateway) && $accountGateway->getPlaidEnabled())
    <a href="https://plaid.com/products/auth/" target="_blank" style="display:none" id="secured_by_plaid"><img src="{{ URL::to('images/plaid-logowhite.svg') }}">{{ trans('texts.secured_by_plaid') }}</a>
    <script src="https://cdn.plaid.com/link/stable/link-initialize.js"></script>
    @endif
@stop