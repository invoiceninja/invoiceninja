@extends('header')

@section('head')
    @parent

        @include('money_script')
@stop

@section('content')
	
	{!! Former::open($url)->addClass('warn-on-exit')->method($method) !!}

	@if ($expense)
		{!! Former::populate($expense) !!}
        {!! Former::populateField('should_be_invoiced', intval($expense->should_be_invoiced)) !!}
        {!! Former::hidden('public_id') !!}
	@endif

    <div class="panel panel-default">
        <div class="panel-body">
            <div class="row">
                <div class="col-md-6">
    				{!! Former::select('vendor_id')->addOption('', '')
                            ->data_bind('combobox: vendor_id')
                            ->label(trans('texts.vendor'))
                            ->addGroupClass('vendor-select') !!}

                    {!! Former::text('amount')
                            ->label(trans('texts.amount'))
                            ->data_bind("value: amount, valueUpdate: 'afterkeydown'")
                            ->addGroupClass('amount')
                            ->append($account->present()->currencyCode) !!}

                    {!! Former::text('expense_date')
                            ->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))
                            ->addGroupClass('expense_date')
                            ->label(trans('texts.date'))
                            ->append('<i class="glyphicon glyphicon-calendar"></i>') !!}

                    {!! Former::select('client_id')
                            ->addOption('', '')
                            ->label(trans('texts.client'))
                            ->data_bind('combobox: client_id')
                            ->addGroupClass('client-select') !!}

                    @if (!$expense || ($expense && !$expense->invoice_id))
                        {!! Former::checkbox('should_be_invoiced')
                                ->text(trans('texts.should_be_invoiced'))
                                ->data_bind('checked: should_be_invoiced() || client_id(), enable: !client_id()')
                                ->label(' ') !!}<br/>
                    @endif

                    <span style="display:none" data-bind="visible: !client_id()">
                        {!! Former::select('currency_id')->addOption('','')
                                ->data_bind('combobox: currency_id, disable: true')
                                ->fromQuery($currencies, 'name', 'id') !!}
                    </span>
                    <span style="display:none;" data-bind="visible: client_id">
                        {!! Former::plaintext('test')
                                ->value('<span data-bind="html: currencyName"></span>')
                                ->style('min-height:46px')
                                ->label(trans('texts.currency_id')) !!}
                    </span>

                    {!! Former::text('exchange_rate')
                            ->data_bind("value: exchange_rate, enable: enableExchangeRate, valueUpdate: 'afterkeydown'") !!}

                    {!! Former::text('converted_amount')
                            ->addGroupClass('converted-amount')
                            ->data_bind("value: convertedAmount, enable: enableExchangeRate")
                            ->append('<span data-bind="html: currencyCode"></span>') !!}

	            </div>
                <div class="col-md-6">

                    {!! Former::textarea('public_notes')->rows(9) !!}
                    {!! Former::textarea('private_notes')->rows(9) !!}
                </div>
            </div>
        </div>
    </div>

	<center class="buttons">
        {!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/expenses'))->appendIcon(Icon::create('remove-circle')) !!}
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
	</center>

	{!! Former::close() !!}

    <script type="text/javascript">

        var vendors = {!! $vendors !!};
        var clients = {!! $clients !!};

        var clientMap = {};
        for (var i=0; i<clients.length; i++) {
            var client = clients[i];
            clientMap[client.public_id] = client;
        }

        function onClientChange() {
            var clientId = $('select#client_id').val();
            var client = clientMap[clientId];
            if (client) {
                model.currency_id(client.currency_id);
            }
        }

        $(function() {

            var $vendorSelect = $('select#vendor_id');
            for (var i = 0; i < vendors.length; i++) {
                var vendor = vendors[i];
                $vendorSelect.append(new Option(getClientDisplayName(vendor), vendor.public_id));
            }
            $vendorSelect.combobox();

            $('#expense_date').datepicker('update', new Date());

            $('.expense_date .input-group-addon').click(function() {
                toggleDatePicker('expense_date');
            });

            var $clientSelect = $('select#client_id');
            for (var i=0; i<clients.length; i++) {
                var client = clients[i];
                $clientSelect.append(new Option(getClientDisplayName(client), client.public_id));
            }
            $clientSelect.combobox().change(function() {
                onClientChange();
            });

            @if ($data)
                // this means we failed so we'll reload the previous state
                window.model = new ViewModel({!! $data !!});
            @else
                // otherwise create blank model
                window.model = new ViewModel({!! $expense !!});

                ko.applyBindings(model);
            @endif

            @if (!$expense && $clientPublicId)
                onClientChange();
            @endif

            @if (!$vendorPublicId)
                $('.vendor-select input.form-control').focus();
            @else
                $('#amount').focus();
            @endif
        });

        var ViewModel = function(data) {
            var self = this;

            self.client_id = ko.observable({{ $clientPublicId }});
            self.vendor_id = ko.observable({{ $vendorPublicId }});
            self.currency_id = ko.observable();
            self.amount = ko.observable();
            self.exchange_rate = ko.observable(1);
            self.should_be_invoiced = ko.observable();
            self.account_currency_id = ko.observable({{ $account->getCurrencyId() }});

            if (data) {
                ko.mapping.fromJS(data, {}, this);
            }

            self.convertedAmount = ko.computed({
                read: function () {
                    return roundToTwo(self.amount() * self.exchange_rate()).toFixed(2);
                },
                write: function(value) {
                    self.exchange_rate(roundToFour(value / self.amount()));
                }
            }, self);

            self.currencyCode = ko.computed(function() {
                var currencyId = self.currency_id() || self.account_currency_id();
                var currency = currencyMap[currencyId];
                return currency.code;
            });

            self.currencyName = ko.computed(function() {
                var currencyId = self.currency_id() || self.account_currency_id();
                var currency = currencyMap[currencyId];
                console.log(currencyId);
                return currency.name;
            });

            self.enableExchangeRate = ko.computed(function() {
                if (!self.currency_id()) {
                    return false;
                }
                return self.currency_id() != self.account_currency_id();
            })
        };

    </script>

@stop