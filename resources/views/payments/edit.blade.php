@extends('header')

@section('head')
    @parent

    @include('money_script')

    <style type="text/css">
        .input-group-addon {
            min-width: 40px;
        }
    </style>
@stop

@section('content')

	{!! Former::open($payment ? $url : '')
        ->addClass('col-lg-10 col-lg-offset-1 warn-on-exit main-form')
        ->onsubmit('return onFormSubmit(event)')
        ->method($method)
        ->autocomplete('off')
        ->rules(array(
    		'client' => 'required',
    		'invoice' => 'required',
    		'amount' => 'required',
    	)) !!}

    @if ($payment)
        {!! Former::populate($payment) !!}
    @else
        @if ($account->payment_type_id)
            {!! Former::populateField('payment_type_id', $account->payment_type_id) !!}
        @endif
    @endif

    <span style="display:none">
        {!! Former::text('public_id') !!}
        {!! Former::text('action') !!}
    </span>

	<div class="row">
		<div class="col-lg-10 col-lg-offset-1">

            <div class="panel panel-default">
            <div class="panel-body">

            @if ($payment)
             {!! Former::plaintext()->label('client')->value($payment->client->present()->link) !!}
             {!! Former::plaintext()->label('invoice')->value($payment->invoice->present()->link) !!}
             {!! Former::plaintext()->label('amount')->value($payment->present()->amount) !!}
            @else
			 {!! Former::select('client')->addOption('', '')->addGroupClass('client-select') !!}
			 {!! Former::select('invoice')->addOption('', '')->addGroupClass('invoice-select') !!}
			 {!! Former::text('amount')->append('<span data-bind="html: paymentCurrencyCode"></span>') !!}

             @if (isset($paymentTypeId) && $paymentTypeId)
               {!! Former::populateField('payment_type_id', $paymentTypeId) !!}
             @endif
            @endif

            @if (!$payment || !$payment->account_gateway_id)
			 {!! Former::select('payment_type_id')
                    ->addOption('','')
                    ->fromQuery($paymentTypes, 'name', 'id')
                    ->addGroupClass('payment-type-select') !!}
            @endif

			{!! Former::text('payment_date')
                        ->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT))
                        ->addGroupClass('payment_date')
                        ->append('<i class="glyphicon glyphicon-calendar"></i>') !!}
			{!! Former::text('transaction_reference') !!}
            {!! Former::textarea('private_notes') !!}


            @if (!$payment || ($payment && ! $payment->isExchanged()))
                {!! Former::checkbox('convert_currency')
                        ->text(trans('texts.convert_currency'))
                        ->data_bind('checked: convert_currency')
                        ->label(' ')
                        ->value(1) !!}
            @endif

            <div style="display:none" data-bind="visible: enableExchangeRate">
                <br/>
                {!! Former::select('exchange_currency_id')->addOption('','')
                        ->label(trans('texts.currency'))
                        ->data_placeholder(Utils::getFromCache($account->getCurrencyId(), 'currencies')->name)
                        ->data_bind('combobox: exchange_currency_id, disable: true')
                        ->fromQuery($currencies, 'name', 'id') !!}
                {!! Former::text('exchange_rate')
                        ->data_bind("value: exchange_rate, enable: enableExchangeRate, valueUpdate: 'afterkeydown'") !!}
                {!! Former::text('')
                        ->label(trans('texts.converted_amount'))
                        ->data_bind("value: convertedAmount, enable: enableExchangeRate")
                        ->append('<span data-bind="html: exchangeCurrencyCode"></span>') !!}
            </div>


            @if (!$payment)
                {!! Former::checkbox('email_receipt')
                        ->onchange('onEmailReceiptChange()')
                        ->label('&nbsp;')
                        ->text(trans('texts.email_receipt'))
                        ->value(1) !!}
            @endif

            </div>
            </div>

		</div>
	</div>

    @if (Auth::user()->canCreateOrEdit(ENTITY_PAYMENT, $payment))
	<center class="buttons">
        {!! Button::normal(trans('texts.cancel'))->appendIcon(Icon::create('remove-circle'))->asLinkTo(HTMLUtils::previousUrl('/payments'))->large() !!}
        @if (!$payment || !$payment->is_deleted)
            {!! Button::success(trans('texts.save'))->withAttributes(['id' => 'saveButton'])->appendIcon(Icon::create('floppy-disk'))->submit()->large() !!}
        @endif

        @if ($payment)
            {!! DropdownButton::normal(trans('texts.more_actions'))
                  ->withContents($actions)
                  ->large()
                  ->dropup() !!}
        @endif

	</center>
    @endif

    @include('partials/refund_payment')

	{!! Former::close() !!}

	<script type="text/javascript">

    var canSubmitPayment = true;
	var invoices = {!! $invoices !!};
	var clients = {!! $clients !!};

    var clientMap = {};
    var invoiceMap = {};
    var invoicesForClientMap = {};
    var statuses = [];

    @foreach (cache('invoiceStatus') as $status)
        statuses[{{ $status->id }}] = "{{ $status->getTranslatedName() }}";
    @endforeach

    for (var i=0; i<clients.length; i++) {
        var client = clients[i];
        clientMap[client.public_id] = client;
    }

    for (var i=0; i<invoices.length; i++) {
      var invoice = invoices[i];
      var client = invoice.client;

      if (!invoicesForClientMap.hasOwnProperty(client.public_id)) {
        invoicesForClientMap[client.public_id] = [];
      }

      invoicesForClientMap[client.public_id].push(invoice);
      invoiceMap[invoice.public_id] = invoice;
    }

	$(function() {
        @if (! empty($totalCredit))
            $('#payment_type_id option:contains("{{ trans('texts.apply_credit') }}")').text("{{ trans('texts.apply_credit') }} | {{ $totalCredit}}");
        @endif

        @if (Input::old('data'))
            // this means we failed so we'll reload the previous state
            window.model = new ViewModel({!! $data !!});
        @else
            // otherwise create blank model
            window.model = new ViewModel({!! $payment !!});
        @endif
        ko.applyBindings(model);

        $('#amount').change(function() {
            var amount = $('#amount').val();
            model.amount(NINJA.parseFloat(amount));
        })

        @if ($payment)
          $('#payment_date').datepicker('update', '{{ $payment->payment_date }}')
          @if ($payment->payment_type_id != PAYMENT_TYPE_CREDIT)
            $("#payment_type_id option[value='{{ PAYMENT_TYPE_CREDIT }}']").remove();
          @endif
        @else
          $('#payment_date').datepicker('update', new Date());
		  populateInvoiceComboboxes({{ $clientPublicId }}, {{ $invoicePublicId }});
        @endif

		$('#payment_type_id').combobox();

        @if (!$payment && !$clientPublicId)
            $('.client-select input.form-control').focus();
        @elseif (!$payment && !$invoicePublicId)
            $('.invoice-select input.form-control').focus();
        @elseif (!$payment)
            $('#amount').focus();
        @endif

        $('.payment_date .input-group-addon').click(function() {
            toggleDatePicker('payment_date');
        });

        $('#exchange_currency_id').on('change', function() {
            setTimeout(function() {
                model.updateExchangeRate();
            }, 1);
        })

        if (isStorageSupported()) {
            if (localStorage.getItem('last:send_email_receipt')) {
                $('#email_receipt').prop('checked', true);
            }
        }
	});

    function onFormSubmit(event) {
        if (! canSubmitPayment) {
            return false;
        }

        @if ($payment)
            $('#saveButton').attr('disabled', true);
            canSubmitPayment = false;
            return true;
        @else
            // warn if amount is more than balance/credit will be created
            var invoiceId = $('input[name=invoice]').val();
            var invoice = invoiceMap[invoiceId];
            var amount = $('#amount').val();

            if (NINJA.parseFloat(amount) <= invoice.balance || confirm("{{ trans('texts.amount_greater_than_balance') }}")) {
                $('#saveButton').attr('disabled', true);
                canSubmitPayment = false;
                submitAjax();
                return false;
            } else {
                return false;
            }
        @endif
    }

    function submitAjax() {
        $.post('{{ url($url) }}', $('.main-form').serialize(), function(data) {
            if (data && data.toLowerCase().indexOf('http') === 0) {
                NINJA.formIsChanged = false;
                location.href = data;
            } else {
                handleSaveFailed();
            }
        }).fail(function(data) {
            handleSaveFailed(data);
        });
    }

    function handleSaveFailed(data) {
		$('#saveButton').attr('disabled', false);
        canSubmitPayment = true;

		var error = '{{ trans('texts.error_refresh_page') }}';
		if (data) {
		    error = firstJSONError(data.responseJSON);
		}

		swal({title: '{{ trans('texts.an_error_occurred') }}', text: error});
	}

    function submitAction(action) {
        $('#action').val(action);
        $('.main-form').submit();
    }

    function submitForm_payment(action) {
        submitAction(action);
    }

    function onDeleteClick() {
        sweetConfirm(function() {
            submitAction('delete');
        });
    }

    function onEmailReceiptChange() {
        if (! isStorageSupported()) {
            return;
        }
        var checked = $('#email_receipt').is(':checked');
        localStorage.setItem('last:send_email_receipt', checked ? true : '');
    }


    var ViewModel = function(data) {
        var self = this;

        self.client_id = ko.observable();
        self.exchange_currency_id = ko.observable();
        self.amount = ko.observable();
        self.exchange_rate = ko.observable(1);
        self.convert_currency = ko.observable({{ ($payment && $payment->isExchanged()) ? 'true' : 'false' }});

        if (data) {
            ko.mapping.fromJS(data, self.mapping, this);
            self.exchange_rate(roundSignificant(self.exchange_rate()));
        }

        self.account_currency_id = ko.observable({{ $account->getCurrencyId() }});

        self.convertedAmount = ko.computed({
            read: function () {
                return roundToTwo(self.amount() * self.exchange_rate()).toFixed(2);
            },
            write: function(value) {
                var amount = NINJA.parseFloat(value) / self.amount();
                self.exchange_rate(roundSignificant(amount));
            }
        }, self);


        self.updateExchangeRate = function() {
            var fromCode = self.paymentCurrencyCode();
            var toCode = self.exchangeCurrencyCode();
            if (currencyMap[fromCode].exchange_rate && currencyMap[toCode].exchange_rate) {
                var rate = fx.convert(1, {
                    from: fromCode,
                    to: toCode,
                });
                self.exchange_rate(roundToFour(rate, true));
            } else {
                self.exchange_rate(1);
            }
        }

        self.getCurrency = function(currencyId) {
            return currencyMap[currencyId || self.account_currency_id()];
        };

        self.exchangeCurrencyCode = ko.computed(function() {
            var currency = self.getCurrency(self.exchange_currency_id());
            return currency ? currency.code : '';
        });

        self.paymentCurrencyCode = ko.computed(function() {
            var client = clientMap[self.client_id()];
            if (client && client.currency_id) {
                var currencyId = client.currency_id;
            } else {
                var currencyId = self.account_currency_id();
            }
            var currency = self.getCurrency(currencyId);
            return currency ? currency.code : '';
        });

        self.enableExchangeRate = ko.computed(function() {
            if (self.convert_currency()) {
                return true;
            }
            /*
            var expenseCurrencyId = self.expense_currency_id() || self.account_currency_id();
            var invoiceCurrencyId = self.invoice_currency_id() || self.account_currency_id();
            return expenseCurrencyId != invoiceCurrencyId
                || invoiceCurrencyId != self.account_currency_id()
                || expenseCurrencyId != self.account_currency_id();
            */
        })
    };

    function populateInvoiceComboboxes(clientId, invoiceId) {
      var $clientSelect = $('select#client');
      $clientSelect.append(new Option('', ''));
      for (var i=0; i<clients.length; i++) {
        var client = clients[i];
        var clientName = getClientDisplayName(client);
        if (!clientName) {
            continue;
        }
        $clientSelect.append(new Option(clientName, client.public_id));
      }

      if (clientId) {
        $clientSelect.val(clientId);
      }

      $clientSelect.combobox({highlighter: comboboxHighlighter});
      $clientSelect.on('change', function(e) {
        var clientId = $('input[name=client]').val();
        var invoiceId = $('input[name=invoice]').val();
        var invoice = invoiceMap[invoiceId];
        if (invoice && invoice.client.public_id == clientId) {
          e.preventDefault();
          return;
        }
        setComboboxValue($('.invoice-select'), '', '');
        $invoiceCombobox = $('select#invoice');
        $invoiceCombobox.find('option').remove().end().combobox('refresh');
        $invoiceCombobox.append(new Option('', ''));
        var list = clientId ? (invoicesForClientMap.hasOwnProperty(clientId) ? invoicesForClientMap[clientId] : []) : invoices;
        for (var i=0; i<list.length; i++) {
          var invoice = list[i];
          var client = clientMap[invoice.client.public_id];
          if (!client || !getClientDisplayName(client)) continue; // client is deleted/archived
          $invoiceCombobox.append(new Option(invoice.invoice_number + ' - ' + statuses[invoice.invoice_status.id] + ' - ' +
                    getClientDisplayName(client) + ' - ' + formatMoneyInvoice(invoice.amount, invoice) + ' | ' +
                    formatMoneyInvoice(invoice.balance, invoice),  invoice.public_id));
        }
        $('select#invoice').combobox('refresh');
        $('#amount').val('');

        if (window.model) {
            model.amount('');
            model.client_id(clientId);
            setTimeout(function() {
                model.updateExchangeRate();
            }, 1);
        }
      });

      if (clientId) {
        $clientSelect.trigger('change');
      }

      var $invoiceSelect = $('select#invoice').on('change', function(e) {
        $clientCombobox = $('select#client');
        var invoiceId = $('input[name=invoice]').val();
        if (invoiceId) {
          var invoice = invoiceMap[invoiceId];
          var client = clientMap[invoice.client.public_id];
          invoice.client = client;
          setComboboxValue($('.client-select'), client.public_id, getClientDisplayName(client));
          var amount = parseFloat(invoice.balance);
          $('#amount').val(amount.toFixed(2));
          model.amount(amount);
      } else {
          $('#amount').val('');
          model.amount('');
      }
        model.client_id(client ? client.public_id : 0);
        setTimeout(function() {
            model.updateExchangeRate();
        }, 1);
      });

      $invoiceSelect.combobox({highlighter: comboboxHighlighter});

      if (invoiceId) {
        var invoice = invoiceMap[invoiceId];
        if (invoice) {
            var client = clientMap[invoice.client.public_id];
            invoice.client = client;
            setComboboxValue($('.invoice-select'), invoice.public_id, (invoice.invoice_number + ' - ' +
                    invoice.invoice_status.name + ' - ' + getClientDisplayName(client) + ' - ' +
                    formatMoneyInvoice(invoice.amount, invoice) + ' | ' + formatMoneyInvoice(invoice.balance, invoice)));
            $invoiceSelect.trigger('change');
        }
      } else if (clientId) {
        var client = clientMap[clientId];
        setComboboxValue($('.client-select'), client.public_id, getClientDisplayName(client));
        $clientSelect.trigger('change');
      } else {
        $clientSelect.trigger('change');
      }
    }


	</script>

@stop
