@extends('public.header')

@section('content')

    @include('payments.payment_css')

    <div class="container">
        <p>&nbsp;</p>

        <div class="panel panel-default">
        <div class="panel-body">

            <p>&nbsp;</p>
            <p>&nbsp;</p>

            {!! Former::vertical_open()
                    ->autocomplete('on')
                    ->addClass('payment-form')
                    ->id('payment-form')
                    ->rules(array(
                        'name' => 'required',
                        'vat_number' => 'required',
                        'address1' => 'required',
                        'city' => 'required',
                        'state' => 'required',
                        'postal_code' => 'required',
                        'country_id' => 'required',
                    )) !!}


            @if ($client)
                {{ Former::populate($client) }}
                {{ Former::populateField('first_name', $contact->first_name) }}
                {{ Former::populateField('last_name', $contact->last_name) }}
                {{ Former::populateField('email', $contact->email) }}
                @if (!$client->country_id && $client->account->country_id)
                    {{ Former::populateField('country_id', $client->account->country_id) }}
                @endif
            @endif

            <h3>{{ trans('texts.client_information') }}</h3>
            <hr class="form-legend"/>

            <div style="padding-bottom: 22px;">

                <div class="row">
                    <div class="col-md-6">
                        {!! Former::text('name')
                                ->placeholder(trans('texts.name'))
                                ->label('') !!}
                    </div>
                    @if ($account->vat_number)
                        <div class="col-md-6">
                            {!! Former::text('vat_number')
                                    ->placeholder(trans('texts.vat_number'))
                                    ->label('') !!}
                        </div>
                    @endif
                </div>
            </div>

            <h3>{{ trans('texts.billing_address') }}</h3>
            <hr class="form-legend"/>

            <div style="padding-bottom: 22px;" class="billing-address">
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
                </div>

                <p>&nbsp;</p>

                <center>
                    {!! Button::normal(strtoupper(trans('texts.cancel') ))->asLinkTo('/client/dashboard')->large() !!} &nbsp;
                    {!! Button::success(strtoupper(trans('texts.save') ))->submit()->large() !!}
                </center>


            </div>

            {!! Former::close() !!}

        </div>
        </div>


    </div>



    <script type="text/javascript">

        $(function() {
            $('#country_id, #shipping_country_id').combobox();
            $('#first_name').focus();
        });

    </script>


@stop
