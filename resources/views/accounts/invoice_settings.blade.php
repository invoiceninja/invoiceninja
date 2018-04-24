@extends('header')

@section('head')
    @parent

        <style type="text/css">
            .iframe_url {
                display: none;
            }
            .input-group-addon div.checkbox {
                display: inline;
            }
            .tab-content .pad-checkbox span.input-group-addon {
                padding-right: 30px;
            }
        </style>
@stop

@section('content')
	@parent
    @include('accounts.nav', ['selected' => ACCOUNT_INVOICE_SETTINGS, 'advanced' => true])

    {!! Former::open()->rules(['iframe_url' => 'url'])->addClass('warn-on-exit') !!}
    {{ Former::populate($account) }}
    {{ Former::populateField('auto_convert_quote', intval($account->auto_convert_quote)) }}
    {{ Former::populateField('auto_archive_quote', intval($account->auto_archive_quote)) }}
    {{ Former::populateField('auto_email_invoice', intval($account->auto_email_invoice)) }}
    {{ Former::populateField('auto_archive_invoice', intval($account->auto_archive_invoice)) }}
    {{ Former::populateField('custom_invoice_taxes1', intval($account->custom_invoice_taxes1)) }}
    {{ Former::populateField('custom_invoice_taxes2', intval($account->custom_invoice_taxes2)) }}
    {{ Former::populateField('share_counter', intval($account->share_counter)) }}
    @foreach (App\Models\Account::$customFields as $field)
        {{ Former::populateField("custom_fields[$field]", $account->customLabel($field)) }}
    @endforeach

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.generated_numbers') !!}</h3>
        </div>
        <div class="panel-body form-padding-right">

            <div role="tabpanel">
                <ul class="nav nav-tabs" role="tablist" style="border: none">
                    <li role="presentation" class="active">
                        <a href="#invoice_number" aria-controls="invoice_number" role="tab" data-toggle="tab">{{ trans('texts.invoice_number') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#quote_number" aria-controls="quote_number" role="tab" data-toggle="tab">{{ trans('texts.quote_number') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#client_number" aria-controls="client_number" role="tab" data-toggle="tab">{{ trans('texts.client_number') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#credit_number" aria-controls="credit_number" role="tab" data-toggle="tab">{{ trans('texts.credit_number') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#options" aria-controls="options" role="tab" data-toggle="tab">{{ trans('texts.options') }}</a>
                    </li>
                </ul>
            </div>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="invoice_number">
                    <div class="panel-body">
                        {!! Former::inline_radios('invoice_number_type')
                                ->onchange("onNumberTypeChange('invoice')")
                                ->label(trans('texts.type'))
                                ->radios([
                                    trans('texts.prefix') => ['value' => 'prefix', 'name' => 'invoice_number_type'],
                                    trans('texts.pattern') => ['value' => 'pattern', 'name' => 'invoice_number_type'],
                                ])->check($account->invoice_number_pattern ? 'pattern' : 'prefix') !!}

                        {!! Former::text('invoice_number_prefix')
                                ->addGroupClass('invoice-prefix')
                                ->label(trans('texts.prefix')) !!}
                        {!! Former::text('invoice_number_pattern')
                                ->appendIcon('question-sign')
                                ->addGroupClass('invoice-pattern')
                                ->label(trans('texts.pattern'))
                                ->addGroupClass('number-pattern') !!}
                        {!! Former::text('invoice_number_counter')
                                ->label(trans('texts.counter'))
                                ->help(trans('texts.invoice_number_help') . ' ' .
                                    trans('texts.next_invoice_number', ['number' => $account->previewNextInvoiceNumber()])) !!}
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="quote_number">
                    <div class="panel-body">
                        {!! Former::inline_radios('quote_number_type')
                                ->onchange("onNumberTypeChange('quote')")
                                ->label(trans('texts.type'))
                                ->radios([
                                    trans('texts.prefix') => ['value' => 'prefix', 'name' => 'quote_number_type'],
                                    trans('texts.pattern') => ['value' => 'pattern', 'name' => 'quote_number_type'],
                                ])->check($account->quote_number_pattern ? 'pattern' : 'prefix') !!}

                        {!! Former::text('quote_number_prefix')
                                ->addGroupClass('quote-prefix')
                                ->label(trans('texts.prefix')) !!}
                        {!! Former::text('quote_number_pattern')
                                ->appendIcon('question-sign')
                                ->addGroupClass('quote-pattern')
                                ->addGroupClass('number-pattern')
                                ->label(trans('texts.pattern')) !!}
                        {!! Former::text('quote_number_counter')
                                ->label(trans('texts.counter'))
                                ->addGroupClass('pad-checkbox')
                                ->append(Former::checkbox('share_counter')->raw()->value(1)
                                ->onclick('setQuoteNumberEnabled()') . ' ' . trans('texts.share_invoice_counter'))
                                ->help(trans('texts.quote_number_help') . ' ' .
                                    trans('texts.next_quote_number', ['number' => $account->previewNextInvoiceNumber(ENTITY_QUOTE)])) !!}


                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="client_number">
                    <div class="panel-body">
                        {!! Former::checkbox('client_number_enabled')
                                ->label('client_number')
                                ->onchange('onClientNumberEnabled()')
                                ->text('enable')
                                ->value(1)
                                ->check($account->client_number_counter > 0) !!}

                        <div id="clientNumberDiv" style="display:none">

                            {!! Former::inline_radios('client_number_type')
                                    ->onchange("onNumberTypeChange('client')")
                                    ->label(trans('texts.type'))
                                    ->radios([
                                        trans('texts.prefix') => ['value' => 'prefix', 'name' => 'client_number_type'],
                                        trans('texts.pattern') => ['value' => 'pattern', 'name' => 'client_number_type'],
                                    ])->check($account->client_number_pattern ? 'pattern' : 'prefix') !!}

                            {!! Former::text('client_number_prefix')
                                    ->addGroupClass('client-prefix')
                                    ->label(trans('texts.prefix')) !!}
                            {!! Former::text('client_number_pattern')
                                    ->appendIcon('question-sign')
                                    ->addGroupClass('client-pattern')
                                    ->addGroupClass('client-number-pattern')
                                    ->label(trans('texts.pattern')) !!}
                            {!! Former::text('client_number_counter')
                                    ->label(trans('texts.counter'))
                                    ->addGroupClass('pad-checkbox')
                                    ->help(trans('texts.client_number_help') . ' ' .
                                        trans('texts.next_client_number', ['number' => $account->getNextNumber() ?: '0001'])) !!}

                        </div>
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="credit_number">
                    <div class="panel-body">

                        {!! Former::checkbox('credit_number_enabled')
                                ->label('credit_number')
                                ->onchange('onCreditNumberEnabled()')
                                ->text('enable')
                                ->value(1)
                                ->check($account->credit_number_counter > 0) !!}

                        <div id="creditNumberDiv" style="display:none">

                            {!! Former::inline_radios('credit_number_type')
                                    ->onchange("onNumberTypeChange('credit')")
                                    ->label(trans('texts.type'))
                                    ->radios([
                                        trans('texts.prefix') => ['value' => 'prefix', 'name' => 'credit_number_type'],
                                        trans('texts.pattern') => ['value' => 'pattern', 'name' => 'credit_number_type'],
                                    ])->check($account->credit_number_pattern ? 'pattern' : 'prefix') !!}

                            {!! Former::text('credit_number_prefix')
                                    ->addGroupClass('credit-prefix')
                                    ->label(trans('texts.prefix')) !!}
                            {!! Former::text('credit_number_pattern')
                                    ->appendIcon('question-sign')
                                    ->addGroupClass('credit-pattern')
                                    ->addGroupClass('credit-number-pattern')
                                    ->label(trans('texts.pattern')) !!}
                            {!! Former::text('credit_number_counter')
                                    ->label(trans('texts.counter'))
                                    ->addGroupClass('pad-checkbox')
                                    ->help(trans('texts.credit_number_help') . ' ' .
                                        trans('texts.next_credit_number', ['number' => $account->getNextNumber(new \App\Models\Credit()) ?: '0001'])) !!}
                        </div>
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="options">
                    <div class="panel-body">

                        {!! Former::text('invoice_number_padding')
                                ->help('padding_help') !!}

                        {!! Former::text('recurring_invoice_number_prefix')
                                ->label(trans('texts.recurring_prefix'))
                                ->help(trans('texts.recurring_invoice_number_prefix_help')) !!}

                        {!! Former::select('reset_counter_frequency_id')
                                ->onchange('onResetFrequencyChange()')
                                ->label('reset_counter')
                                ->addOption(trans('texts.never'), '')
                                ->options(\App\Models\Frequency::selectOptions())
                                ->help('reset_counter_help') !!}

                        {!! Former::text('reset_counter_date')
                                    ->label('next_reset')
                                    ->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))
                                    ->addGroupClass('reset_counter_date_group')
                                    ->append('<i class="glyphicon glyphicon-calendar"></i>')
                                    ->data_date_start_date($account->formatDate($account->getDateTime())) !!}

                    </div>
                </div>
            </div>

        </div>
    </div>


    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.custom_fields') !!}</h3>
        </div>
        <div class="panel-body form-padding-right">

            <div role="tabpanel">
                <ul class="nav nav-tabs" role="tablist" style="border: none">
                    <li role="presentation" class="active">
                        <a href="#product_fields" aria-controls="product_fields" role="tab" data-toggle="tab">{{ trans('texts.products') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#client_fields" aria-controls="client_fields" role="tab" data-toggle="tab">{{ trans('texts.clients') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#invoice_fields" aria-controls="invoice_fields" role="tab" data-toggle="tab">{{ trans('texts.invoices') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#task_fields" aria-controls="expense_fields" role="tab" data-toggle="tab">{{ trans('texts.tasks') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#expense_fields" aria-controls="task_fields" role="tab" data-toggle="tab">{{ trans('texts.expenses') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#company_fields" aria-controls="company_fields" role="tab" data-toggle="tab">{{ trans('texts.company') }}</a>
                    </li>
                </ul>
            </div>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="product_fields">
                    <div class="panel-body">

                        {!! Former::text('custom_fields[product1]')
                                ->label('product_field')
                                ->data_lpignore('true') !!}
                        {!! Former::text('custom_fields[product2]')
                                ->label('product_field')
                                ->data_lpignore('true')
                                ->help(trans('texts.custom_product_fields_help') . ' ' . trans('texts.custom_fields_tip')) !!}

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="client_fields">
                    <div class="panel-body">

                        {!! Former::text('custom_fields[client1]')
                                ->label('client_field') !!}
                        {!! Former::text('custom_fields[client2]')
                                ->label('client_field')
                                ->help(trans('texts.custom_client_fields_helps') . ' ' . trans('texts.custom_fields_tip')) !!}

                        <br/>

                        {!! Former::text('custom_fields[contact1]')
                                ->label('contact_field') !!}
                        {!! Former::text('custom_fields[contact2]')
                                ->label('contact_field')
                                ->help(trans('texts.custom_contact_fields_help') . ' ' . trans('texts.custom_fields_tip')) !!}

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="invoice_fields">
                    <div class="panel-body">

                        {!! Former::text('custom_fields[invoice_text1]')
                                ->label('invoice_field') !!}
                        {!! Former::text('custom_fields[invoice_text2]')
                                ->label('invoice_field')
                                ->help(trans('texts.custom_invoice_fields_helps') . ' ' . trans('texts.custom_fields_tip')) !!}

                        {!! Former::text('custom_fields[invoice1]')
                                ->label('invoice_surcharge')
                                ->addGroupClass('pad-checkbox')
                                ->append(Former::checkbox('custom_invoice_taxes1')
                                            ->value(1)
                                            ->raw() . trans('texts.charge_taxes')) !!}

                        {!! Former::text('custom_fields[invoice2]')
                                ->label('invoice_surcharge')
                                ->addGroupClass('pad-checkbox')
                                ->append(Former::checkbox('custom_invoice_taxes2')
                                            ->value(1)
                                            ->raw() . trans('texts.charge_taxes'))
                                            ->help(trans('texts.custom_invoice_charges_helps')) !!}

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="task_fields">
                    <div class="panel-body">

                        {!! Former::text('custom_fields[task1]')
                                ->label('task_field') !!}
                        {!! Former::text('custom_fields[task2]')
                                ->label('task_field')
                                ->help(trans('texts.custom_task_fields_help') . ' ' . trans('texts.custom_fields_tip')) !!}

                        <br/>

                        {!! Former::text('custom_fields[project1]')
                                ->label('project_field') !!}
                        {!! Former::text('custom_fields[project2]')
                                ->label('project_field')
                                ->help(trans('texts.custom_project_fields_help') . ' ' . trans('texts.custom_fields_tip')) !!}

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="expense_fields">
                    <div class="panel-body">

                        {!! Former::text('custom_fields[expense1]')
                                ->label(trans('texts.expense_field')) !!}
                        {!! Former::text('custom_fields[expense2]')
                                ->label(trans('texts.expense_field'))
                                ->help(trans('texts.custom_expense_fields_help') . ' ' . trans('texts.custom_fields_tip')) !!}

                        <br/>

                        {!! Former::text('custom_fields[vendor1]')
                                ->label(trans('texts.vendor_field')) !!}
                        {!! Former::text('custom_fields[vendor2]')
                                ->label(trans('texts.vendor_field'))
                                ->help(trans('texts.custom_vendor_fields_help') . ' ' . trans('texts.custom_fields_tip')) !!}

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="company_fields">
                    <div class="panel-body">

                        {!! Former::text('custom_fields[account1]')
                                ->label(trans('texts.company_field')) !!}
                        {!! Former::text('custom_value1')
                                ->label(trans('texts.field_value')) !!}
                        <p>&nbsp;</p>
                        {!! Former::text('custom_fields[account2]')
                                ->label(trans('texts.company_field')) !!}
                        {!! Former::text('custom_value2')
                                ->label(trans('texts.field_value'))
                                ->help(trans('texts.custom_account_fields_helps')) !!}

                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.workflow_settings') !!}</h3>
        </div>
        <div class="panel-body form-padding-right">

            <div role="tabpanel">
                <ul class="nav nav-tabs" role="tablist" style="border: none">
                    <li role="presentation" class="active">
                        <a href="#invoice_workflow" aria-controls="invoice_workflow" role="tab" data-toggle="tab">{{ trans('texts.invoice_workflow') }}</a>
                    </li>
                    <li role="presentation">
                        <a href="#quote_workflow" aria-controls="quote_workflow" role="tab" data-toggle="tab">{{ trans('texts.quote_workflow') }}</a>
                    </li>
                </ul>
            </div>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="invoice_workflow">
                    <div class="panel-body">
                        {!! Former::checkbox('auto_email_invoice')
                                ->text(trans('texts.enable'))
                                ->blockHelp(trans('texts.auto_email_invoice_help'))
                                ->value(1) !!}

                        {!! Former::checkbox('auto_archive_invoice')
                                ->text(trans('texts.enable'))
                                ->blockHelp(trans('texts.auto_archive_invoice_help'))
                                ->value(1) !!}
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="quote_workflow">
                    <div class="panel-body">
                        {!! Former::checkbox('auto_convert_quote')
                                ->text(trans('texts.enable'))
                                ->blockHelp(trans('texts.auto_convert_quote_help'))
                                ->value(1) !!}

                        {!! Former::checkbox('auto_archive_quote')
                                ->text(trans('texts.enable'))
                                ->blockHelp(trans('texts.auto_archive_quote_help'))
                                ->value(1) !!}
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">{!! trans('texts.defaults') !!}</h3>
      </div>
        <div class="panel-body" style="min-height:350px">

            <div role="tabpanel">
                <ul class="nav nav-tabs" role="tablist" style="border: none">
                    <li role="presentation" class="active"><a href="#invoice_terms" aria-controls="invoice_terms" role="tab" data-toggle="tab">{{ trans('texts.invoice_terms') }}</a></li>
                    <li role="presentation"><a href="#invoice_footer" aria-controls="invoice_footer" role="tab" data-toggle="tab">{{ trans('texts.invoice_footer') }}</a></li>
                    <li role="presentation"><a href="#quote_terms" aria-controls="quote_terms" role="tab" data-toggle="tab">{{ trans('texts.quote_terms') }}</a></li>
                    @if ($account->hasFeature(FEATURE_DOCUMENTS))
                        <li role="presentation"><a href="#documents" aria-controls="documents" role="tab" data-toggle="tab">
                            {{ trans('texts.documents') }}
                            @if ($count = $account->defaultDocuments->count())
                                ({{ $count }})
                            @endif
                        </a></li>
                    @endif
                </ul>
            </div>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="invoice_terms">
                    <div class="panel-body">
                        {!! Former::textarea('invoice_terms')
                                ->label(trans('texts.default_invoice_terms'))
                                ->rows(8)
                                ->raw() !!}
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="invoice_footer">
                    <div class="panel-body">
                        {!! Former::textarea('invoice_footer')
                                ->label(trans('texts.default_invoice_footer'))
                                ->rows(8)
                                ->raw() !!}
                        @if ($account->hasFeature(FEATURE_REMOVE_CREATED_BY) && ! $account->isTrial())
                            <div class="help-block">
                                {{ trans('texts.invoice_footer_help')}}
                            </div>
                        @endif
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="quote_terms">
                    <div class="panel-body">
                        {!! Former::textarea('quote_terms')
                                ->label(trans('texts.default_quote_terms'))
                                ->rows(8)
                                ->raw() !!}
                    </div>
                </div>
                @if ($account->hasFeature(FEATURE_DOCUMENTS))
                    <div role="tabpanel" class="tab-pane" id="documents">
                        <div class="panel-body">
                            <div class="form-group">
                                <div class="col-lg-12 col-sm-12">
                                    <div role="tabpanel" class="tab-pane" id="attached-documents" style="position:relative;z-index:9">
                                        <div id="document-upload">
                                            <div class="dropzone">
                                                <!--
                                                <div data-bind="foreach: documents">
                                                    <input type="hidden" name="document_ids[]" data-bind="value: public_id"/>
                                                </div>
                                                -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>



    @if (Auth::user()->hasFeature(FEATURE_INVOICE_SETTINGS))
        <center>
            {!! Button::success(trans('texts.save'))->large()->submit()->appendIcon(Icon::create('floppy-disk')) !!}
        </center>
    @endif

    <div class="modal fade" id="patternHelpModal" tabindex="-1" role="dialog" aria-labelledby="patternHelpModalLabel" aria-hidden="true">
        <div class="modal-dialog" style="min-width:150px">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="patternHelpModalLabel">{{ trans('texts.pattern_help_title') }}</h4>
                </div>

                <div class="container" style="width: 100%; padding-bottom: 0px !important">
                <div class="panel panel-default">
                <div class="panel-body">
                    <p>{{ trans('texts.pattern_help_1') }}</p>
                    <p>{{ trans('texts.pattern_help_2') }}</p>
                    <ul>
                        @foreach (\App\Models\Invoice::$patternFields as $field)
                            @if ($field == 'date:')
                                <li>{$date:format} - {!! link_to(PHP_DATE_FORMATS, trans('texts.see_options'), ['target' => '_blank']) !!}</li>
                            @elseif (strpos($field, 'client') !== false)
                                <li class="hide-client">{${{ $field }}}</li>
                            @else
                                <li>{${{ $field }}}</li>
                            @endif
                        @endforeach
                    </ul>
                    <p class="hide-client">{{ trans('texts.pattern_help_3', [
                            'example' => '{$year}-{$counter}',
                            'value' => date('Y') . '-0001'
                        ]) }}</p>
                </div>
                </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">{{ trans('texts.close') }}</button>
                </div>

            </div>
        </div>
    </div>


	{!! Former::close() !!}


	<script type="text/javascript">

  	function setQuoteNumberEnabled() {
			var disabled = $('#share_counter').prop('checked');
			$('#quote_number_counter').prop('disabled', disabled);
			$('#quote_number_counter').val(disabled ? '' : {!! json_encode($account->quote_number_counter) !!});
		}

    function onNumberTypeChange(entityType) {
        var val = $('input[name=' + entityType + '_number_type]:checked').val();
        if (val == 'prefix') {
            $('.' + entityType + '-prefix').show();
            $('.' + entityType + '-pattern').hide();
        } else {
            $('.' + entityType + '-prefix').hide();
            $('.' + entityType + '-pattern').show();
        }
    }

    function onClientNumberEnabled() {
        var enabled = $('#client_number_enabled').is(':checked');
        if (enabled) {
            $('#clientNumberDiv').show();
            $('#client_number_counter').val({{ $account->client_number_counter ?: 1 }});
        } else {
            $('#clientNumberDiv').hide();
            $('#client_number_counter').val(0);
        }
    }

    function onCreditNumberEnabled() {
        var enabled = $('#credit_number_enabled').is(':checked');
        if (enabled) {
            $('#creditNumberDiv').show();
            $('#credit_number_counter').val({{ $account->credit_number_counter ?: 1 }});
        } else {
            $('#creditNumberDiv').hide();
            $('#credit_number_counter').val(0);
        }
    }

    function onResetFrequencyChange() {
        var val = $('#reset_counter_frequency_id').val();
        if (val) {
            $('.reset_counter_date_group').show();
        } else {
            $('.reset_counter_date_group').hide();
        }
    }

    $('.number-pattern .input-group-addon').click(function() {
        $('.hide-client').show();
        $('#patternHelpModal').modal('show');
    });

    $('.client-number-pattern .input-group-addon').click(function() {
        $('.hide-client').hide();
        $('#patternHelpModal').modal('show');
    });

    $('.credit-number-pattern .input-group-addon').click(function() {
        $('.hide-client').hide();
        $('#patternHelpModal').modal('show');
    });


    var defaultDocuments = {!! $account->defaultDocuments()->get() !!};

    $(function() {
    	setQuoteNumberEnabled();
        onNumberTypeChange('invoice');
        onNumberTypeChange('quote');
        onNumberTypeChange('client');
        onNumberTypeChange('credit');
        onClientNumberEnabled();
        onCreditNumberEnabled();
        onResetFrequencyChange();

        $('#reset_counter_date').datepicker('update', '{{ Utils::fromSqlDate($account->reset_counter_date) ?: 'new Date()' }}');
        $('.reset_counter_date_group .input-group-addon').click(function() {
            toggleDatePicker('reset_counter_date');
        });

        @if ($account->hasFeature(FEATURE_DOCUMENTS))
            @include('partials.dropzone', ['documentSource' => 'defaultDocuments', 'isDefault' => true])
        @endif
    });

	</script>


@stop

@section('onReady')
    $('#custom_invoice_label1').focus();
@stop
