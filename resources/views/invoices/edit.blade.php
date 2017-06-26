@extends('header')

@section('head_css')
	@parent

	<link href="{{ asset('css/lightbox.css') }}" rel="stylesheet" type="text/css"/>
	<link href="{{ asset('css/quill.snow.css') }}" rel="stylesheet" type="text/css"/>

	<style type="text/css">
        select.tax-select {
            width: 50%;
            float: left;
        }

        #scrollable-dropdown-menu .tt-menu {
            max-height: 150px;
            overflow-y: auto;
            overflow-x: hidden;
        }

		.signature-wrapper .tooltip-inner {
			width: 600px;
			max-width: 600px;
			padding: 20px;
		}

    </style>
@stop

@section('head')
	@parent

    @include('money_script')

    @foreach ($account->getFontFolders() as $font)
        <script src="{{ asset('js/vfs_fonts/'.$font.'.js') }}" type="text/javascript"></script>
    @endforeach
	<script src="{{ asset('pdf.built.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>
    <script src="{{ asset('js/lightbox.min.js') }}" type="text/javascript"></script>
	<script src="{{ asset('js/quill.min.js') }}" type="text/javascript"></script>
@stop

@section('content')
    @if ($errors->first('invoice_items'))
        <div class="alert alert-danger">{{ trans($errors->first('invoice_items')) }}</div>
    @endif

	@if ($invoice->id)
		<ol class="breadcrumb">
		@if ($invoice->is_recurring)
			<li>{!! link_to('invoices', trans('texts.recurring_invoice')) !!}</li>
		@else
			<li>{!! link_to(($entityType == ENTITY_QUOTE ? 'quotes' : 'invoices'), trans('texts.' . ($entityType == ENTITY_QUOTE ? 'quotes' : 'invoices'))) !!}</li>
			<li class="active">{{ $invoice->invoice_number }}</li>
		@endif
		@if ($invoice->is_recurring && $invoice->isSent() && (! $invoice->last_sent_date || $invoice->last_sent_date == '0000-00-00'))
			{!! $invoice->present()->statusLabel(trans('texts.pending')) !!}
		@else
			{!! $invoice->present()->statusLabel !!}
		@endif
		</ol>
	@endif

	{!! Former::open($url)
            ->method($method)
            ->addClass('warn-on-exit main-form search') // 'search' prevents LastPass auto-fill http://stackoverflow.com/a/30921628/497368
            ->autocomplete('off')
            ->onsubmit('return onFormSubmit(event)')
            ->rules(array(
        		'client' => 'required',
                'invoice_number' => 'required',
                'invoice_date' => 'required',
        		'product_key' => 'max:255'
        	)) !!}

    @include('partials.autocomplete_fix')

	<input type="submit" style="display:none" name="submitButton" id="submitButton">

	<div data-bind="with: invoice">
    <div class="panel panel-default">
    <div class="panel-body">

    <div class="row" style="min-height:195px" onkeypress="formEnterClick(event)">
    	<div class="col-md-4" id="col_1">

    		@if ($invoice->id || $data)
				<div class="form-group">
					<label for="client" class="control-label col-lg-4 col-sm-4"><b>{{ trans('texts.client') }}</b></label>
					<div class="col-lg-8 col-sm-8">
                        <h4>
                            <span data-bind="text: getClientDisplayName(ko.toJS(client()))"></span>
                            @if ($invoice->client->is_deleted)
                                &nbsp;&nbsp;<div class="label label-danger">{{ trans('texts.deleted') }}</div>
                            @endif
                        </h4>

                        @can('view', $invoice->client)
                            @can('edit', $invoice->client)
                                <a id="editClientLink" class="pointer" data-bind="click: $root.showClientForm">{{ trans('texts.edit_client') }}</a> |
                            @endcan
                            {!! link_to('/clients/'.$invoice->client->public_id, trans('texts.view_client'), ['target' => '_blank']) !!}
                        @endcan
					</div>
				</div>
				<div style="display:none">
    		@endif

            {!! Former::select('client')
					->addOption('', '')
					->data_bind("dropdown: client, dropdownOptions: {highlighter: comboboxHighlighter, matcher: comboboxMatcher}")
					->addClass('client-input')
					->addGroupClass('client_select closer-row') !!}

			<div class="form-group" style="margin-bottom: 8px">
				<div class="col-lg-8 col-sm-8 col-lg-offset-4 col-sm-offset-4">
					<a id="createClientLink" class="pointer" data-bind="click: $root.showClientForm, html: $root.clientLinkText"></a>
                    <span data-bind="visible: $root.invoice().client().public_id() > 0" style="display:none">|
                        <a data-bind="attr: {href: '{{ url('/clients') }}/' + $root.invoice().client().public_id()}" target="_blank">{{ trans('texts.view_client') }}</a>
                    </span>
				</div>
			</div>

			@if ($invoice->id || $data)
				</div>
			@endif

			<div data-bind="with: client" class="invoice-contact">
				<div style="display:none" class="form-group" data-bind="visible: contacts().length > 0, foreach: contacts">
					<div class="col-lg-8 col-lg-offset-4 col-sm-offset-4">
						<label class="checkbox" data-bind="attr: {for: $index() + '_check'}, visible: email.display" onclick="refreshPDF(true)">
                            <input type="hidden" value="0" data-bind="attr: {name: 'client[contacts][' + $index() + '][send_invoice]'}">
							<input type="checkbox" value="1" data-bind="visible: email() || first_name() || last_name(), checked: send_invoice, attr: {id: $index() + '_check', name: 'client[contacts][' + $index() + '][send_invoice]'}">
							<span data-bind="html: email.display"></span>
                        </label>
                        @if ( ! $invoice->is_deleted && ! $invoice->client->is_deleted)
                        <span data-bind="visible: !$root.invoice().is_recurring()">
                            <span data-bind="html: $data.view_as_recipient"></span>&nbsp;&nbsp;
                            @if (Utils::isConfirmed())
	                            <span style="vertical-align:text-top;color:red" class="fa fa-exclamation-triangle"
	                                    data-bind="visible: $data.email_error, tooltip: {title: $data.email_error}"></span>
	                            <span style="vertical-align:text-top;padding-top:2px" class="fa fa-info-circle"
	                                    data-bind="visible: $data.invitation_status, tooltip: {title: $data.invitation_status, html: true},
	                                    style: {color: $data.info_color}"></span>
								<span class="signature-wrapper">&nbsp;
								<span style="vertical-align:text-top;color:#888" class="fa fa-user"
	                                    data-bind="visible: $data.invitation_signature_svg, tooltip: {title: $data.invitation_signature_svg, html: true}"></span>
								</span>
                            @endif
                        </span>
                        @endif
					</div>
				</div>
			</div>

		</div>
		<div class="col-md-4" id="col_2">
			<div data-bind="visible: !is_recurring()">
				{!! Former::text('invoice_date')->data_bind("datePicker: invoice_date, valueUpdate: 'afterkeydown'")->label(trans("texts.{$entityType}_date"))
							->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))->appendIcon('calendar')->addGroupClass('invoice_date') !!}
				{!! Former::text('due_date')->data_bind("datePicker: due_date, valueUpdate: 'afterkeydown'")->label($account->getLabel($invoice->getDueDateLabel()))
							->placeholder($invoice->exists || $invoice->isQuote() ? ' ' : $account->present()->dueDatePlaceholder())
							->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))->appendIcon('calendar')->addGroupClass('due_date') !!}
				{!! Former::text('partial')->data_bind("value: partial, valueUpdate: 'afterkeydown'")->onkeyup('onPartialChange()')
							->addGroupClass('partial')!!}
			</div>
            @if ($entityType == ENTITY_INVOICE)
			<div data-bind="visible: is_recurring" style="display: none">
				{!! Former::select('frequency_id')->label('frequency')->options($frequencies)->data_bind("value: frequency_id")
                        ->appendIcon('question-sign')->addGroupClass('frequency_id')->onchange('onFrequencyChange()') !!}
				{!! Former::text('start_date')->data_bind("datePicker: start_date, valueUpdate: 'afterkeydown'")
							->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))->appendIcon('calendar')->addGroupClass('start_date') !!}
				{!! Former::text('end_date')->data_bind("datePicker: end_date, valueUpdate: 'afterkeydown'")
							->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))->appendIcon('calendar')->addGroupClass('end_date') !!}
                {!! Former::select('recurring_due_date')->label(trans('texts.due_date'))->options($recurringDueDates)->data_bind("value: recurring_due_date")->appendIcon('question-sign')->addGroupClass('recurring_due_date') !!}
			</div>
            @endif

            @if ($account->showCustomField('custom_invoice_text_label1', $invoice))
                {!! Former::text('custom_text_value1')->label($account->custom_invoice_text_label1 ?: ' ')->data_bind("value: custom_text_value1, valueUpdate: 'afterkeydown'") !!}
            @endif
		</div>

		<div class="col-md-4" id="col_2">
            <span data-bind="visible: !is_recurring()">
            {!! Former::text('invoice_number')
                        ->label(trans("texts.{$entityType}_number_short"))
                        ->onchange('checkInvoiceNumber()')
                        ->addGroupClass('invoice-number')
                        ->data_bind("value: invoice_number, valueUpdate: 'afterkeydown'") !!}
            </span>
            <span data-bind="visible: is_recurring()" style="display: none">
                <div data-bind="visible: !(auto_bill() == {{AUTO_BILL_OPT_IN}} &amp;&amp; client_enable_auto_bill()) &amp;&amp; !(auto_bill() == {{AUTO_BILL_OPT_OUT}} &amp;&amp; !client_enable_auto_bill())" style="display: none">
                {!! Former::select('auto_bill')
                        ->data_bind("value: auto_bill, valueUpdate: 'afterkeydown', event:{change:function(){if(auto_bill()==".AUTO_BILL_OPT_IN.")client_enable_auto_bill(0);if(auto_bill()==".AUTO_BILL_OPT_OUT.")client_enable_auto_bill(1)}}")
                        ->options([
                            AUTO_BILL_OFF => trans('texts.off'),
                            AUTO_BILL_OPT_IN => trans('texts.opt_in'),
                            AUTO_BILL_OPT_OUT => trans('texts.opt_out'),
                            AUTO_BILL_ALWAYS => trans('texts.always'),
                        ]) !!}
                </div>
                <input type="hidden" name="client_enable_auto_bill" data-bind="attr: { value: client_enable_auto_bill() }" />
                <div class="form-group" data-bind="visible: auto_bill() == {{AUTO_BILL_OPT_IN}} &amp;&amp; client_enable_auto_bill()">
                    <div class="col-sm-4 control-label">{{trans('texts.auto_bill')}}</div>
                    <div class="col-sm-8" style="padding-top:10px;padding-bottom:9px">
                        {{trans('texts.opted_in')}} - <a href="#" data-bind="click:function(){client_enable_auto_bill(false)}">({{trans('texts.disable')}})</a>
                    </div>
                </div>
                <div class="form-group" data-bind="visible: auto_bill() == {{AUTO_BILL_OPT_OUT}} &amp;&amp; !client_enable_auto_bill()">
                    <div class="col-sm-4 control-label">{{trans('texts.auto_bill')}}</div>
                    <div class="col-sm-8" style="padding-top:10px;padding-bottom:9px">
                        {{trans('texts.opted_out')}} - <a href="#" data-bind="click:function(){client_enable_auto_bill(true)}">({{trans('texts.enable')}})</a>
                    </div>
                </div>
            </span>
			{!! Former::text('po_number')->label($account->getLabel('po_number', 'po_number_short'))->data_bind("value: po_number, valueUpdate: 'afterkeydown'") !!}
			{!! Former::text('discount')->data_bind("value: discount, valueUpdate: 'afterkeydown'")
					->addGroupClass('discount-group')->type('number')->min('0')->step('any')->append(
						Former::select('is_amount_discount')->addOption(trans('texts.discount_percent'), '0')
						->addOption(trans('texts.discount_amount'), '1')->data_bind("value: is_amount_discount")->raw()
			) !!}

            @if ($account->showCustomField('custom_invoice_text_label2', $invoice))
                {!! Former::text('custom_text_value2')->label($account->custom_invoice_text_label2 ?: ' ')->data_bind("value: custom_text_value2, valueUpdate: 'afterkeydown'") !!}
            @endif

            @if ($entityType == ENTITY_INVOICE)
            <div class="form-group" style="margin-bottom: 8px">
                <div class="col-lg-8 col-sm-8 col-sm-offset-4 smaller" style="padding-top: 10px">
                	@if ($invoice->recurring_invoice)
                        {!! trans('texts.created_by_invoice', ['invoice' => link_to('/invoices/'.$invoice->recurring_invoice->public_id, trans('texts.recurring_invoice'))]) !!}
    				@elseif ($invoice->id)
                        @if (isset($lastSent) && $lastSent)
                            {!! trans('texts.last_sent_on', ['date' => link_to('/invoices/'.$lastSent->public_id, $invoice->last_sent_date, ['id' => 'lastSent'])]) !!} <br/>
                        @endif
                        @if ($invoice->is_recurring && $invoice->getNextSendDate())
                           {!! trans('texts.next_send_on', ['date' => '<span data-bind="tooltip: {title: \''.$invoice->getPrettySchedule().'\', html: true}">'.$account->formatDate($invoice->getNextSendDate()).
                                '<span class="glyphicon glyphicon-info-sign" style="padding-left:10px;color:#B1B5BA"></span></span>']) !!}
                            @if ($invoice->getDueDate())
                                <br>
                                {!! trans('texts.next_due_on', ['date' => '<span>'.$account->formatDate($invoice->getDueDate($invoice->getNextSendDate())).'</span>']) !!}
                            @endif
                        @endif
                    @endif
                </div>
            </div>
            @endif
		</div>
	</div>

	<div class="table-responsive" style="padding-top:4px">
	<table class="table invoice-table">
		<thead>
			<tr>
				<th style="min-width:32px;" class="hide-border"></th>
				<th style="min-width:120px;width:25%">{{ $invoiceLabels['item'] }}</th>
				<th style="width:100%">{{ $invoiceLabels['description'] }}</th>
                @if ($account->showCustomField('custom_invoice_item_label1'))
                    <th style="min-width:120px">{{ $account->custom_invoice_item_label1 }}</th>
                @endif
                @if ($account->showCustomField('custom_invoice_item_label2'))
                    <th style="min-width:120px">{{ $account->custom_invoice_item_label2 }}</th>
                @endif
				<th style="min-width:120px" data-bind="text: costLabel">{{ $invoiceLabels['unit_cost'] }}</th>
				<th style="{{ $account->hide_quantity ? 'display:none' : 'min-width:120px' }}" data-bind="text: qtyLabel">{{ $invoiceLabels['quantity'] }}</th>
				<th style="min-width:{{ $account->enable_second_tax_rate ? 180 : 120 }}px;display:none;" data-bind="visible: $root.invoice_item_taxes.show">{{ trans('texts.tax') }}</th>
				<th style="min-width:120px;">{{ trans('texts.line_total') }}</th>
				<th style="min-width:32px;" class="hide-border"></th>
			</tr>
		</thead>
		<tbody data-bind="sortable: { data: invoice_items, afterMove: onDragged }">
			<tr data-bind="event: { mouseover: showActions, mouseout: hideActions }" class="sortable-row">
				<td class="hide-border td-icon">
					<i style="display:none" data-bind="visible: actionsVisible() &amp;&amp;
                        $parent.invoice_items().length > 1" class="fa fa-sort"></i>
				</td>
				<td>
                    <div id="scrollable-dropdown-menu">
                        <input id="product_key" type="text" data-bind="productTypeahead: product_key, items: $root.products, key: 'product_key', valueUpdate: 'afterkeydown', attr: {name: 'invoice_items[' + $index() + '][product_key]'}" class="form-control invoice-item handled"/>
                    </div>
				</td>
				<td>
					<textarea data-bind="value: notes, valueUpdate: 'afterkeydown', attr: {name: 'invoice_items[' + $index() + '][notes]'}"
                        rows="1" cols="60" style="resize: vertical;height:42px" class="form-control word-wrap"></textarea>
                        <input type="text" data-bind="value: task_public_id, attr: {name: 'invoice_items[' + $index() + '][task_public_id]'}" style="display: none"/>
						<input type="text" data-bind="value: expense_public_id, attr: {name: 'invoice_items[' + $index() + '][expense_public_id]'}" style="display: none"/>
						<input type="text" data-bind="value: invoice_item_type_id, attr: {name: 'invoice_items[' + $index() + '][invoice_item_type_id]'}" style="display: none"/>
				</td>
                @if ($account->showCustomField('custom_invoice_item_label1'))
                    <td>
                        <input data-bind="value: custom_value1, valueUpdate: 'afterkeydown', attr: {name: 'invoice_items[' + $index() + '][custom_value1]'}" class="form-control invoice-item"/>
                    </td>
                @endif
                @if ($account->showCustomField('custom_invoice_item_label2'))
                    <td>
                        <input data-bind="value: custom_value2, valueUpdate: 'afterkeydown', attr: {name: 'invoice_items[' + $index() + '][custom_value2]'}" class="form-control invoice-item"/>
                    </td>
                @endif
				<td>
					<input data-bind="value: prettyCost, valueUpdate: 'afterkeydown', attr: {name: 'invoice_items[' + $index() + '][cost]'}"
                        style="text-align: right" class="form-control invoice-item"/>
				</td>
				<td style="{{ $account->hide_quantity ? 'display:none' : '' }}">
					<input data-bind="value: prettyQty, valueUpdate: 'afterkeydown', attr: {name: 'invoice_items[' + $index() + '][qty]'}"
                        style="text-align: right" class="form-control invoice-item" name="quantity"/>
				</td>
				<td style="display:none;" data-bind="visible: $root.invoice_item_taxes.show">
	                    {!! Former::select('')
	                            ->addOption('', '')
	                            ->options($taxRateOptions)
	                            ->data_bind('value: tax1, event:{change:onTax1Change}')
	                            ->addClass($account->enable_second_tax_rate ? 'tax-select' : '')
	                            ->raw() !!}
                    <input type="text" data-bind="value: tax_name1, attr: {name: 'invoice_items[' + $index() + '][tax_name1]'}" style="display:none">
                    <input type="text" data-bind="value: tax_rate1, attr: {name: 'invoice_items[' + $index() + '][tax_rate1]'}" style="display:none">
                    <div data-bind="visible: $root.invoice().account.enable_second_tax_rate == '1'">
                        {!! Former::select('')
                                ->addOption('', '')
                                ->options($taxRateOptions)
                                ->data_bind('value: tax2, event:{change:onTax2Change}')
                                ->addClass('tax-select')
                                ->raw() !!}
                    </div>
                    <input type="text" data-bind="value: tax_name2, attr: {name: 'invoice_items[' + $index() + '][tax_name2]'}" style="display:none">
                    <input type="text" data-bind="value: tax_rate2, attr: {name: 'invoice_items[' + $index() + '][tax_rate2]'}" style="display:none">
				</td>
				<td style="text-align:right;padding-top:9px !important" nowrap>
					<div class="line-total" data-bind="text: totals.total"></div>
				</td>
				<td style="cursor:pointer" class="hide-border td-icon">
                    <i style="padding-left:2px" data-bind="click: $parent.removeItem, visible: actionsVisible() &amp;&amp;
                    $index() < ($parent.invoice_items().length - 1) &amp;&amp;
                    $parent.invoice_items().length > 1" class="fa fa-minus-circle redlink" title="Remove item"/>
				</td>
			</tr>
		</tbody>


		<tfoot>
			<tr>
				<td class="hide-border"/>
				<td class="hide-border" colspan="{{ 2 + ($account->showCustomField('custom_invoice_item_label1') ? 1 : 0) + ($account->showCustomField('custom_invoice_item_label2') ? 1 : 0) }}" rowspan="6" style="vertical-align:top">
					<br/>
                    <div role="tabpanel">

                      <ul class="nav nav-tabs" role="tablist" style="border: none">
                        <li role="presentation" class="active"><a href="#public_notes" aria-controls="notes" role="tab" data-toggle="tab">{{ trans('texts.public_notes') }}</a></li>
						<li role="presentation"><a href="#private_notes" aria-controls="terms" role="tab" data-toggle="tab">{{ trans("texts.private_notes") }}</a></li>
                        <li role="presentation"><a href="#terms" aria-controls="terms" role="tab" data-toggle="tab">{{ trans("texts.terms") }}</a></li>
                        <li role="presentation"><a href="#footer" aria-controls="footer" role="tab" data-toggle="tab">{{ trans("texts.footer") }}</a></li>
                        @if ($account->hasFeature(FEATURE_DOCUMENTS))
                            <li role="presentation"><a href="#attached-documents" aria-controls="attached-documents" role="tab" data-toggle="tab">
                                {{ trans("texts.invoice_documents") }}
                                @if ($count = $invoice->countDocuments())
                                    ({{ $count }})
                                @endif
                            </a></li>
                        @endif
                    </ul>

                    <div class="tab-content">
                        <div role="tabpanel" class="tab-pane active" id="public_notes" style="padding-bottom:44px">
                            {!! Former::textarea('public_notes')
									->data_bind("value: public_notes, valueUpdate: 'afterkeydown'")
                            		->label(null)->style('width: 550px')->rows(4) !!}
                        </div>
						<div role="tabpanel" class="tab-pane" id="private_notes" style="padding-bottom:44px">
                            {!! Former::textarea('private_notes')
									->data_bind("value: private_notes, valueUpdate: 'afterkeydown'")
                            		->label(null)->style('width: 550px')->rows(4) !!}
                        </div>
                        <div role="tabpanel" class="tab-pane" id="terms">
                            {!! Former::textarea('terms')
									->data_bind("value:terms, placeholder: terms_placeholder, valueUpdate: 'afterkeydown'")
                            		->label(false)->style('width: 550px')->rows(4)
		                            ->help('<div class="checkbox">
		                                        <label>
		                                            <input name="set_default_terms" type="checkbox" style="width: 24px" data-bind="checked: set_default_terms"/>'.trans('texts.save_as_default_terms').'
		                                        </label>
		                                        <div class="pull-right" data-bind="visible: showResetTerms()">
		                                            <a href="#" onclick="return resetTerms()" title="'. trans('texts.reset_terms_help') .'">' . trans("texts.reset_terms") . '</a>
		                                        </div>
		                                    </div>') !!}
                        </div>
                        <div role="tabpanel" class="tab-pane" id="footer">
                            {!! Former::textarea('invoice_footer')
									->data_bind("value:invoice_footer, placeholder: footer_placeholder, valueUpdate: 'afterkeydown'")
		                            ->label(false)->style('width: 550px')->rows(4)
		                            ->help('<div class="checkbox">
		                                        <label>
		                                            <input name="set_default_footer" type="checkbox" style="width: 24px" data-bind="checked: set_default_footer"/>'.trans('texts.save_as_default_footer').'
		                                        </label>
		                                        <div class="pull-right" data-bind="visible: showResetFooter()">
		                                            <a href="#" onclick="return resetFooter()" title="'. trans('texts.reset_footer_help') .'">' . trans("texts.reset_footer") . '</a>
		                                        </div>
		                                    </div>') !!}
                        </div>
                        @if ($account->hasFeature(FEATURE_DOCUMENTS))
                        <div role="tabpanel" class="tab-pane" id="attached-documents" style="position:relative;z-index:9">
                            <div id="document-upload">
                                <div class="dropzone">
                                    <div data-bind="foreach: documents">
                                        <input type="hidden" name="document_ids[]" data-bind="value: public_id"/>
                                    </div>
                                </div>
                                @if ($invoice->hasExpenseDocuments())
                                    <h4>{{trans('texts.documents_from_expenses')}}</h4>
                                    @foreach($invoice->expenses as $expense)
										@if ($expense->invoice_documents)
	                                        @foreach($expense->documents as $document)
	                                            <div>{{$document->name}}</div>
	                                        @endforeach
										@endif
                                    @endforeach
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

				</td>
				<td class="hide-border" style="display:none" data-bind="visible: $root.invoice_item_taxes.show"/>
				<td colspan="{{ $account->hide_quantity ? 1 : 2 }}">{{ trans('texts.subtotal') }}</td>
				<td style="text-align: right"><span data-bind="text: totals.subtotal"/></td>
			</tr>

			<tr style="display:none" data-bind="visible: discount() != 0">
				<td class="hide-border" colspan="3"/>
				<td style="display:none" class="hide-border" data-bind="visible: $root.invoice_item_taxes.show"/>
				<td colspan="{{ $account->hide_quantity ? 1 : 2 }}">{{ trans('texts.discount') }}</td>
				<td style="text-align: right"><span data-bind="text: totals.discounted"/></td>
			</tr>

            @if ($account->showCustomField('custom_invoice_label1', $invoice) && $invoice->custom_taxes1)
				<tr>
					<td class="hide-border" colspan="3"/>
					<td style="display:none" class="hide-border" data-bind="visible: $root.invoice_item_taxes.show"/>
					<td colspan="{{ $account->hide_quantity ? 1 : 2 }}">{{ $account->custom_invoice_label1 ?: trans('texts.surcharge') }}</td>
					<td style="text-align: right;padding-right: 28px" colspan="2"><input name="custom_value1" class="form-control" data-bind="value: custom_value1, valueUpdate: 'afterkeydown'"/></td>
				</tr>
			@endif

            @if ($account->showCustomField('custom_invoice_label2', $invoice) && $invoice->custom_taxes2)
				<tr>
					<td class="hide-border" colspan="3"/>
					<td style="display:none" class="hide-border" data-bind="visible: $root.invoice_item_taxes.show"/>
					<td colspan="{{ $account->hide_quantity ? 1 : 2 }}">{{ $account->custom_invoice_label2 ?: trans('texts.surcharge') }}</td>
					<td style="text-align: right;padding-right: 28px" colspan="2"><input name="custom_value2" class="form-control" data-bind="value: custom_value2, valueUpdate: 'afterkeydown'"/></td>
				</tr>
			@endif

            <tr style="display:none" data-bind="visible: $root.invoice_item_taxes.show &amp;&amp; totals.hasItemTaxes">
                <td class="hide-border" colspan="4"/>
                @if (!$account->hide_quantity)
                    <td>{{ trans('texts.tax') }}</td>
                @endif
                <td style="min-width:120px"><span data-bind="html: totals.itemTaxRates"/></td>
                <td style="text-align: right"><span data-bind="html: totals.itemTaxAmounts"/></td>
            </tr>

			<tr style="display:none" data-bind="visible: $root.invoice_taxes.show">
				<td class="hide-border" colspan="3"/>
				<td style="display:none" class="hide-border" data-bind="visible: $root.invoice_item_taxes.show"/>
				@if (!$account->hide_quantity)
					<td>{{ trans('texts.tax') }}</td>
				@endif
				<td style="min-width:120px">
                    {!! Former::select('')
                            ->id('taxRateSelect1')
                            ->addOption('', '')
                            ->options($taxRateOptions)
                            ->addClass($account->enable_second_tax_rate ? 'tax-select' : '')
                            ->data_bind('value: tax1, event:{change:onTax1Change}')
                            ->raw() !!}
                    <input type="text" name="tax_name1" data-bind="value: tax_name1" style="display:none">
                    <input type="text" name="tax_rate1" data-bind="value: tax_rate1" style="display:none">
                    <div data-bind="visible: $root.invoice().account.enable_second_tax_rate == '1'">
                    {!! Former::select('')
                            ->addOption('', '')
                            ->options($taxRateOptions)
                            ->addClass('tax-select')
                            ->data_bind('value: tax2, event:{change:onTax2Change}')
                            ->raw() !!}
                    </div>
                    <input type="text" name="tax_name2" data-bind="value: tax_name2" style="display:none">
                    <input type="text" name="tax_rate2" data-bind="value: tax_rate2" style="display:none">
                </td>
				<td style="text-align: right"><span data-bind="text: totals.taxAmount"/></td>
			</tr>

            @if ($account->showCustomField('custom_invoice_label1', $invoice) && !$invoice->custom_taxes1)
				<tr>
					<td class="hide-border" colspan="3"/>
					<td style="display:none" class="hide-border" data-bind="visible: $root.invoice_item_taxes.show"/>
					<td colspan="{{ $account->hide_quantity ? 1 : 2 }}">{{ $account->custom_invoice_label1 ?: trans('texts.surcharge') }}</td>
					<td style="text-align: right;padding-right: 28px" colspan="2"><input name="custom_value1" class="form-control" data-bind="value: custom_value1, valueUpdate: 'afterkeydown'"/></td>
				</tr>
			@endif

            @if ($account->showCustomField('custom_invoice_label2', $invoice) && !$invoice->custom_taxes2)
				<tr>
					<td class="hide-border" colspan="3"/>
					<td style="display:none" class="hide-border" data-bind="visible: $root.invoice_item_taxes.show"/>
					<td colspan="{{ $account->hide_quantity ? 1 : 2 }}">{{ $account->custom_invoice_label2 ?: trans('texts.surcharge') }}</td>
					<td style="text-align: right;padding-right: 28px" colspan="2"><input name="custom_value2" class="form-control" data-bind="value: custom_value2, valueUpdate: 'afterkeydown'"/></td>
				</tr>
			@endif

			@if (!$account->hide_paid_to_date)
				<tr>
					<td class="hide-border" colspan="3"/>
					<td style="display:none" class="hide-border" data-bind="visible: $root.invoice_item_taxes.show"/>
					<td colspan="{{ $account->hide_quantity ? 1 : 2 }}">{{ trans('texts.paid_to_date') }}</td>
					<td style="text-align: right" data-bind="text: totals.paidToDate"></td>
				</tr>
			@endif

			<tr data-bind="style: { 'font-weight': partial() ? 'normal' : 'bold', 'font-size': partial() ? '1em' : '1.05em' }">
				<td class="hide-border" colspan="3"/>
				<td class="hide-border" style="display:none" data-bind="visible: $root.invoice_item_taxes.show"/>
				<td class="hide-border" data-bind="css: {'hide-border': !partial()}" colspan="{{ $account->hide_quantity ? 1 : 2 }}">{{ $entityType == ENTITY_INVOICE ? $invoiceLabels['balance_due'] : trans('texts.total') }}</td>
				<td class="hide-border" data-bind="css: {'hide-border': !partial()}" style="text-align: right"><span data-bind="text: totals.total"></span></td>
			</tr>

			<tr style="font-size:1.05em; display:none; font-weight:bold" data-bind="visible: partial">
				<td class="hide-border" colspan="3"/>
				<td class="hide-border" style="display:none" data-bind="visible: $root.invoice_item_taxes.show"/>
				<td class="hide-border" colspan="{{ $account->hide_quantity ? 1 : 2 }}">{{ $invoiceLabels['partial_due'] }}</td>
				<td class="hide-border" style="text-align: right"><span data-bind="text: totals.partial"></span></td>
			</tr>

		</tfoot>


	</table>
	</div>
    </div>
    </div>

	<p>&nbsp;</p>
	<div class="form-actions">

		<div style="display:none">
			{!! Former::populateField('entityType', $entityType) !!}

			{!! Former::text('entityType') !!}
			{!! Former::text('action') !!}
			{!! Former::text('public_id')->data_bind('value: public_id') !!}
			{!! Former::text('is_public')->data_bind('value: is_public') !!}
            {!! Former::text('is_recurring')->data_bind('value: is_recurring') !!}
            {!! Former::text('is_quote')->data_bind('value: is_quote') !!}
            {!! Former::text('has_tasks')->data_bind('value: has_tasks') !!}
            {!! Former::text('data')->data_bind('value: ko.mapping.toJSON(model)') !!}
			{!! Former::text('has_expenses')->data_bind('value: has_expenses') !!}
            {!! Former::text('pdfupload') !!}
		</div>

		@if (!Utils::hasFeature(FEATURE_MORE_INVOICE_DESIGNS))
			{!! Former::select('invoice_design_id')->style('display:inline;width:150px;background-color:white !important')->raw()->fromQuery($invoiceDesigns, 'name', 'id')->data_bind("value: invoice_design_id")->addOption(trans('texts.more_designs') . '...', '-1') !!}
		@else
			{!! Former::select('invoice_design_id')->style('display:inline;width:150px;background-color:white !important')->raw()->fromQuery($invoiceDesigns, 'name', 'id')->data_bind("value: invoice_design_id") !!}
		@endif

        @if ( $invoice->exists && $invoice->id && ! $invoice->is_recurring)
		    {!! Button::primary(trans('texts.download_pdf'))
                    ->withAttributes(['onclick' => 'onDownloadClick()', 'id' => 'downloadPdfButton'])
                    ->appendIcon(Icon::create('download-alt')) !!}
        @endif

        @if (Auth::user()->canCreateOrEdit(ENTITY_INVOICE, $invoice))
            @if ($invoice->isClientTrashed())
                <!-- do nothing -->
            @else
                @if (!$invoice->is_deleted)
					@if ($invoice->isSent())
						{!! Button::success(trans("texts.save_{$entityType}"))->withAttributes(array('id' => 'saveButton', 'onclick' => 'onSaveClick()'))->appendIcon(Icon::create('floppy-disk')) !!}
					@else
						{!! Button::normal(trans("texts.save_draft"))->withAttributes(array('id' => 'draftButton', 'onclick' => 'onSaveDraftClick()'))->appendIcon(Icon::create('floppy-disk')) !!}
						@if (! $invoice->trashed())
							{!! Button::success(trans($invoice->is_recurring ? "texts.mark_ready" : "texts.mark_sent"))->withAttributes(array('id' => 'saveButton', 'onclick' => 'onMarkSentClick()'))->appendIcon(Icon::create('globe')) !!}
						@endif
					@endif
					@if (! $invoice->trashed())
						{!! Button::info(trans("texts.email_{$entityType}"))->withAttributes(array('id' => 'emailButton', 'onclick' => 'onEmailClick()'))->appendIcon(Icon::create('send')) !!}
					@endif
                    @if ($invoice->id)
                        {!! DropdownButton::normal(trans('texts.more_actions'))->withContents($invoice->present()->moreActions())->dropup() !!}
                    @elseif ( ! $invoice->isQuote() && Request::is('*/clone'))
                        {!! Button::normal(trans($invoice->is_recurring ? 'texts.disable_recurring' : 'texts.enable_recurring'))->withAttributes(['id' => 'recurrButton', 'onclick' => 'onRecurrClick()'])->appendIcon(Icon::create('repeat')) !!}
                    @endif
        	    @endif
                @if ($invoice->trashed())
                    {!! Button::primary(trans('texts.restore'))->withAttributes(['onclick' => 'submitBulkAction("restore")'])->appendIcon(Icon::create('cloud-download')) !!}
                @endif
    		@endif
        @endif

	</div>
	<p>&nbsp;</p>

	@include('invoices.pdf', ['account' => Auth::user()->account, 'hide_pdf' => ! Auth::user()->account->live_preview])

	@if (!Auth::user()->account->isPro())
		<div style="font-size:larger">
			{!! trans('texts.pro_plan_remove_logo', ['link'=>'<a href="javascript:showUpgradeModal()">' . trans('texts.pro_plan_remove_logo_link') . '</a>']) !!}
		</div>
	@endif

	<div class="modal fade" id="clientModal" tabindex="-1" role="dialog" aria-labelledby="clientModalLabel" aria-hidden="true">
	  <div class="modal-dialog" data-bind="css: {'large-dialog': $root.showMore}">
	    <div class="modal-content" style="background-color: #f8f8f8">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	        <h4 class="modal-title" id="clientModalLabel">{{ trans('texts.client') }}</h4>
	      </div>

       <div class="container" style="width: 100%; padding-bottom: 0px !important">
       <div class="panel panel-default">
        <div class="panel-body">

        <div class="row" data-bind="with: client" onkeypress="clientModalEnterClick(event)">
            <div style="margin-left:0px;margin-right:0px" data-bind="css: {'col-md-6': $root.showMore}">

                {!! Former::hidden('client_public_id')->data_bind("value: public_id, valueUpdate: 'afterkeydown',
                            attr: {name: 'client[public_id]'}") !!}
                {!! Former::text('client[name]')
                    ->data_bind("value: name, valueUpdate: 'afterkeydown', attr { placeholder: name.placeholder }")
                    ->label('client_name') !!}

				@if ( ! $account->client_number_counter)
                <span data-bind="visible: $root.showMore">
				@endif

            	{!! Former::text('client[id_number]')
                            ->label('id_number')
							->placeholder($account->clientNumbersEnabled() ? $account->getNextNumber() : ' ')
                            ->data_bind("value: id_number, valueUpdate: 'afterkeydown'") !!}

				@if ( ! $account->client_number_counter)
				</span>
				@endif

				<span data-bind="visible: $root.showMore">
                    {!! Former::text('client[vat_number]')
                            ->label('vat_number')
                            ->data_bind("value: vat_number, valueUpdate: 'afterkeydown'") !!}

                    {!! Former::text('client[website]')
                            ->label('website')
                            ->data_bind("value: website, valueUpdate: 'afterkeydown'") !!}
                    {!! Former::text('client[work_phone]')
                            ->label('work_phone')
                            ->data_bind("value: work_phone, valueUpdate: 'afterkeydown'") !!}

                </span>

                @if (Auth::user()->hasFeature(FEATURE_INVOICE_SETTINGS))
                    @if ($account->custom_client_label1)
                        {!! Former::text('client[custom_value1]')
                            ->label($account->custom_client_label1)
                            ->data_bind("value: custom_value1, valueUpdate: 'afterkeydown'") !!}
                    @endif
                    @if ($account->custom_client_label2)
                        {!! Former::text('client[custom_value2]')
                            ->label($account->custom_client_label2)
                            ->data_bind("value: custom_value2, valueUpdate: 'afterkeydown'") !!}
                    @endif
                @endif

                <span data-bind="visible: $root.showMore">
                    &nbsp;

                    {!! Former::text('client[address1]')
                            ->label(trans('texts.address1'))
                            ->data_bind("value: address1, valueUpdate: 'afterkeydown'") !!}
                    {!! Former::text('client[address2]')
                            ->label(trans('texts.address2'))
                            ->data_bind("value: address2, valueUpdate: 'afterkeydown'") !!}
                    {!! Former::text('client[city]')
                            ->label(trans('texts.city'))
                            ->data_bind("value: city, valueUpdate: 'afterkeydown'") !!}
                    {!! Former::text('client[state]')
                            ->label(trans('texts.state'))
                            ->data_bind("value: state, valueUpdate: 'afterkeydown'") !!}
                    {!! Former::text('client[postal_code]')
                            ->label(trans('texts.postal_code'))
                            ->data_bind("value: postal_code, valueUpdate: 'afterkeydown'") !!}
                    {!! Former::select('client[country_id]')
                            ->label(trans('texts.country_id'))
                            ->addOption('','')->addGroupClass('country_select')
                            ->fromQuery(Cache::get('countries'), 'name', 'id')->data_bind("dropdown: country_id") !!}
                </span>

            </div>
            <div style="margin-left:0px;margin-right:0px" data-bind="css: {'col-md-6': $root.showMore}">

                <div data-bind='template: { foreach: contacts,
                                        beforeRemove: hideContact,
                                        afterAdd: showContact }'>

                    {!! Former::hidden('public_id')->data_bind("value: public_id, valueUpdate: 'afterkeydown',
                            attr: {name: 'client[contacts][' + \$index() + '][public_id]'}") !!}
                    {!! Former::text('first_name')->data_bind("value: first_name, valueUpdate: 'afterkeydown',
                            attr: {name: 'client[contacts][' + \$index() + '][first_name]'}") !!}
                    {!! Former::text('last_name')->data_bind("value: last_name, valueUpdate: 'afterkeydown',
                            attr: {name: 'client[contacts][' + \$index() + '][last_name]'}") !!}
                    {!! Former::text('email')->data_bind("value: email, valueUpdate: 'afterkeydown',
                            attr: {name: 'client[contacts][' + \$index() + '][email]', id:'email'+\$index()}")
                            ->addClass('client-email') !!}
                    {!! Former::text('phone')->data_bind("value: phone, valueUpdate: 'afterkeydown',
                            attr: {name: 'client[contacts][' + \$index() + '][phone]'}") !!}
                    @if ($account->hasFeature(FEATURE_CLIENT_PORTAL_PASSWORD) && $account->enable_portal_password)
                        {!! Former::password('password')->data_bind("value: (typeof password=='function'?password():null)?'-%unchanged%-':'', valueUpdate: 'afterkeydown',
                            attr: {name: 'client[contacts][' + \$index() + '][password]'}")->autocomplete('new-password') !!}
                    @endif
					@if (Auth::user()->hasFeature(FEATURE_INVOICE_SETTINGS))
	                    @if ($account->custom_contact_label1)
	                        {!! Former::text('custom_contact1')->data_bind("value: custom_value1, valueUpdate: 'afterkeydown',
		                            attr: {name: 'client[contacts][' + \$index() + '][custom_value1]'}")
	                            ->label($account->custom_contact_label1) !!}
	                    @endif
	                    @if ($account->custom_contact_label2)
							{!! Former::text('custom_contact2')->data_bind("value: custom_value2, valueUpdate: 'afterkeydown',
									attr: {name: 'client[contacts][' + \$index() + '][custom_value2]'}")
								->label($account->custom_contact_label2) !!}
	                    @endif
	                @endif
                    <div class="form-group">
                        <div class="col-lg-8 col-lg-offset-4">
                            <span class="redlink bold" data-bind="visible: $parent.contacts().length > 1">
                                {!! link_to('#', trans('texts.remove_contact').' -', array('data-bind'=>'click: $parent.removeContact')) !!}
                            </span>
                            <span data-bind="visible: $index() === ($parent.contacts().length - 1)" class="pull-right greenlink bold">
                                {!! link_to('#', trans('texts.add_contact').' +', array('data-bind'=>'click: $parent.addContact')) !!}
                            </span>
                        </div>
                    </div>
                </div>

                <span data-bind="visible: $root.showMore">
                    &nbsp;
                </span>

                {!! Former::select('client[currency_id]')->addOption('','')
                        ->placeholder($account->currency ? $account->currency->name : '')
                        ->label(trans('texts.currency_id'))
                        ->data_bind('value: currency_id')
                        ->fromQuery($currencies, 'name', 'id') !!}

                <span data-bind="visible: $root.showMore">
                {!! Former::select('client[language_id]')->addOption('','')
                        ->placeholder($account->language ? $account->language->name : '')
                        ->label(trans('texts.language_id'))
                        ->data_bind('value: language_id')
                        ->fromQuery($languages, 'name', 'id') !!}
                {!! Former::select('client[payment_terms]')->addOption('','')->data_bind('value: payment_terms')
                        ->fromQuery(\App\Models\PaymentTerm::getSelectOptions(), 'name', 'num_days')
                        ->label(trans('texts.payment_terms'))
                        ->help(trans('texts.payment_terms_help')) !!}
                {!! Former::select('client[size_id]')->addOption('','')->data_bind('value: size_id')
                        ->label(trans('texts.size_id'))
                        ->fromQuery($sizes, 'name', 'id') !!}
                {!! Former::select('client[industry_id]')->addOption('','')->data_bind('value: industry_id')
                        ->label(trans('texts.industry_id'))
                        ->fromQuery($industries, 'name', 'id') !!}
                {!! Former::textarea('client_private_notes')
                        ->label(trans('texts.private_notes'))
                        ->data_bind("value: private_notes, attr:{ name: 'client[private_notes]'}") !!}
                </span>
            </div>
            </div>
        </div>
        </div>
        </div>

         <div class="modal-footer">
            <span class="error-block" id="emailError" style="display:none;float:left;font-weight:bold">{{ trans('texts.provide_name_or_email') }}</span><span>&nbsp;</span>
            <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.cancel') }}</button>
            <button type="button" class="btn btn-default" data-bind="click: $root.showMoreFields, text: $root.showMore() ? '{{ trans('texts.less_fields') }}' : '{{ trans('texts.more_fields') }}'"></button>
            <button id="clientDoneButton" type="button" class="btn btn-primary" data-bind="click: $root.clientFormComplete">{{ trans('texts.done') }}</button>
         </div>

        </div>
      </div>
    </div>

	<div class="modal fade" id="recurringModal" tabindex="-1" role="dialog" aria-labelledby="recurringModalLabel" aria-hidden="true">
	  <div class="modal-dialog" style="min-width:150px">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	        <h4 class="modal-title" id="recurringModalLabel">{{ trans('texts.recurring_invoices') }}</h4>
	      </div>

		  <div class="container" style="width: 100%; padding-bottom: 0px !important">
          <div class="panel panel-default">
			 <div class="panel-body">
				 {!! isset($recurringHelp) ? $recurringHelp : '' !!}
			 </div>
		  </div>
		  </div>

	     <div class="modal-footer">
	      	<button type="button" class="btn btn-primary" data-dismiss="modal">{{ trans('texts.close') }}</button>
	     </div>

	    </div>
	  </div>
	</div>

    <div class="modal fade" id="recurringDueDateModal" tabindex="-1" role="dialog" aria-labelledby="recurringDueDateModalLabel" aria-hidden="true">
	  <div class="modal-dialog" style="min-width:150px">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	        <h4 class="modal-title" id="recurringDueDateModalLabel">{{ trans('texts.recurring_due_dates') }}</h4>
	      </div>

		  <div class="container" style="width: 100%; padding-bottom: 0px !important">
          <div class="panel panel-default">
			 <div class="panel-body">
				 {!! isset($recurringDueDateHelp) ? $recurringDueDateHelp : '' !!}
			</div>
		 </div>
		 </div>

	     <div class="modal-footer">
	      	<button type="button" class="btn btn-primary" data-dismiss="modal">{{ trans('texts.close') }}</button>
	     </div>

	    </div>
	  </div>
	</div>

	@include('partials.email_templates')
	@include('invoices.email')

    {!! Former::close() !!}
    </form>

    {!! Former::open("{$entityType}s/bulk")->addClass('bulkForm') !!}
    {!! Former::populateField('bulk_public_id', $invoice->public_id) !!}
    <span style="display:none">
    {!! Former::text('bulk_public_id') !!}
    {!! Former::text('bulk_action') !!}
    </span>
    {!! Former::close() !!}

    </div>

    @include('invoices.knockout')

	<script type="text/javascript">
    Dropzone.autoDiscover = false;

    var products = {!! $products !!};
	var clients = {!! $clients !!};
    var account = {!! Auth::user()->account !!};
    var dropzone;

    var clientMap = {};
    var $clientSelect = $('select#client');
    var invoiceDesigns = {!! $invoiceDesigns !!};
    var invoiceFonts = {!! $invoiceFonts !!};

	$(function() {
        // create client dictionary
        for (var i=0; i<clients.length; i++) {
            var client = clients[i];
            clientMap[client.public_id] = client;
            @if (! $invoice->id)
	            if (!getClientDisplayName(client)) {
	                continue;
	            }
            @endif
			var clientName = client.name || '';
			for (var j=0; j<client.contacts.length; j++) {
                var contact = client.contacts[j];
                var contactName = getContactDisplayNameWithEmail(contact);
				if (clientName && contactName) {
					clientName += '<br/>  • ';
				}
				if (contactName) {
					clientName += contactName;
				}
            }
            $clientSelect.append(new Option(clientName, client.public_id));
        }

        @if ($data)
            // this means we failed so we'll reload the previous state
            window.model = new ViewModel({!! $data !!});
        @else
            // otherwise create blank model
            window.model = new ViewModel();

            var invoice = {!! $invoice !!};
            ko.mapping.fromJS(invoice, model.invoice().mapping, model.invoice);
            model.invoice().is_recurring({{ $invoice->is_recurring ? '1' : '0' }});
            model.invoice().start_date_orig(model.invoice().start_date());

            @if ($invoice->id)
                var invitationContactIds = {!! json_encode($invitationContactIds) !!};
                var client = clientMap[invoice.client.public_id];
                if (client) { // in case it's deleted
                    for (var i=0; i<client.contacts.length; i++) {
                        var contact = client.contacts[i];
                        contact.send_invoice = invitationContactIds.indexOf(contact.public_id) >= 0;
                    }
                }
                model.invoice().addItem(); // add blank item
            @else
                // set the default account tax rate
                @if ($account->invoice_taxes)
					@if (! empty($account->tax_name1))
						model.invoice().tax_rate1("{{ $account->tax_rate1 }}");
						model.invoice().tax_name1("{{ $account->tax_name1 }}");
					@endif
					@if (! empty($account->tax_name2))
						model.invoice().tax_rate2("{{ $account->tax_rate2 }}");
						model.invoice().tax_name2("{{ $account->tax_name2 }}");
					@endif
                @endif
            @endif

            @if (isset($tasks) && $tasks)
                // move the blank invoice line item to the end
                var blank = model.invoice().invoice_items.pop();
                var tasks = {!! json_encode($tasks) !!};

                for (var i=0; i<tasks.length; i++) {
                    var task = tasks[i];
                    var item = model.invoice().addItem();
                    item.notes(task.description);
                    item.qty(task.duration);
                    item.task_public_id(task.publicId);
                    item.invoice_item_type_id({{ INVOICE_ITEM_TYPE_TASK }});
                }
                model.invoice().invoice_items.push(blank);
                model.invoice().has_tasks(true);
            @endif

            @if (isset($expenses) && $expenses)
                model.expense_currency_id({{ isset($expenseCurrencyId) ? $expenseCurrencyId : 0 }});

                // move the blank invoice line item to the end
                var blank = model.invoice().invoice_items.pop();
                var expenses = {!! $expenses !!}

                for (var i=0; i<expenses.length; i++) {
                    var expense = expenses[i];
                    var item = model.invoice().addItem();
                    item.product_key(expense.expense_category ? expense.expense_category.name : '');
                    item.notes(expense.public_notes);
                    item.qty(1);
                    item.expense_public_id(expense.public_id);
					item.cost(expense.converted_amount);
                    item.tax_rate1(expense.tax_rate1);
                    item.tax_name1(expense.tax_name1);
                    item.tax_rate2(expense.tax_rate2);
                    item.tax_name2(expense.tax_name2);
                }
                model.invoice().invoice_items.push(blank);
                model.invoice().has_expenses(true);
            @endif

        @endif

        // display blank instead of '0'
        if (!NINJA.parseFloat(model.invoice().discount())) model.invoice().discount('');
        if (!NINJA.parseFloat(model.invoice().partial())) model.invoice().partial('');
        if (!model.invoice().custom_value1()) model.invoice().custom_value1('');
        if (!model.invoice().custom_value2()) model.invoice().custom_value2('');

        ko.applyBindings(model);
        onItemChange(true);


		$('#country_id').combobox().on('change', function(e) {
			var countryId = $('input[name=country_id]').val();
            var country = _.findWhere(countries, {id: countryId});
			if (country) {
                model.invoice().client().country = country;
                model.invoice().client().country_id(countryId);
            } else {
				model.invoice().client().country = false;
				model.invoice().client().country_id(0);
			}
		});

		$('[rel=tooltip]').tooltip({'trigger':'manual'});

		$('#invoice_date, #due_date, #start_date, #end_date, #last_sent_date').datepicker();

		@if ($invoice->client && !$invoice->id)
			$('input[name=client]').val({{ $invoice->client->public_id }});
		@endif

		var $input = $('select#client');
		$input.combobox().on('change', function(e) {
            var oldId = model.invoice().client().public_id();
            var clientId = parseInt($('input[name=client]').val(), 10) || 0;
            if (clientId > 0) {
                var selected = clientMap[clientId];
				model.loadClient(selected);
                // we enable searching by contact but the selection must be the client
                $('.client-input').val(getClientDisplayName(selected));
                // if there's an invoice number pattern we'll apply it now
                setInvoiceNumber(selected);
                refreshPDF(true);
			} else if (oldId) {
				model.loadClient($.parseJSON(ko.toJSON(new ClientModel())));
				model.invoice().client().country = false;
                refreshPDF(true);
			}
		});

		// If no clients exists show the client form when clicking on the client select input
		if (clients.length === 0) {
			$('.client_select input.form-control').on('click', function() {
				model.showClientForm();
			});
		}

		$('#invoice_footer, #terms, #public_notes, #invoice_number, #invoice_date, #due_date, #start_date, #po_number, #discount, #currency_id, #invoice_design_id, #recurring, #is_amount_discount, #partial, #custom_text_value1, #custom_text_value2').change(function() {
            $('#downloadPdfButton').attr('disabled', true);
			setTimeout(function() {
				refreshPDF(true);
			}, 1);
		});

        $('.frequency_id .input-group-addon').click(function() {
            showLearnMore();
        });

        $('.recurring_due_date .input-group-addon').click(function() {
            showRecurringDueDateLearnMore();
        });

        var fields = ['invoice_date', 'due_date', 'start_date', 'end_date', 'last_sent_date'];
        for (var i=0; i<fields.length; i++) {
            var field = fields[i];
            (function (_field) {
                $('.' + _field + ' .input-group-addon').click(function() {
                    toggleDatePicker(_field);
                });
            })(field);
        }

        if (model.invoice().client().public_id() || {{ $invoice->id || count($clients) == 0 ? '1' : '0' }}) {
            // do nothing
        } else {
            $('.client_select input.form-control').focus();
        }

		$('#clientModal').on('shown.bs.modal', function () {
            $('#client\\[name\\]').focus();
		}).on('hidden.bs.modal', function () {
			if (model.clientBackup) {
				model.loadClient(model.clientBackup);
				refreshPDF(true);
			}
		})

		$('#relatedActions > button:first').click(function() {
			onPaymentClick();
		});

		$('label.radio').addClass('radio-inline');

		@if ($invoice->client->id)
			$input.trigger('change');
		@else
			refreshPDF(true);
		@endif

		var client = model.invoice().client();
		setComboboxValue($('.client_select'),
			client.public_id(),
			client.name.display());

        @if (isset($tasks) && $tasks)
            NINJA.formIsChanged = true;
        @endif

        @if (isset($expenses) && $expenses)
            NINJA.formIsChanged = true;
        @endif

        applyComboboxListeners();

        @if (Auth::user()->account->hasFeature(FEATURE_DOCUMENTS))
        $('.main-form').submit(function(){
            if($('#document-upload .dropzone .fallback input').val())$(this).attr('enctype', 'multipart/form-data')
            else $(this).removeAttr('enctype')
        })

        // Initialize document upload
        window.dropzone = false;
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            if (window.dropzone) {
                return;
            }

            var target = $(e.target).attr('href') // activated tab
            if (target != '#attached-documents') {
                return;
            }

            window.dropzone = new Dropzone('#document-upload .dropzone', {
                url:{!! json_encode(url('documents')) !!},
                params:{
                    _token:"{{ Session::getToken() }}"
                },
                acceptedFiles:{!! json_encode(implode(',',\App\Models\Document::$allowedMimes)) !!},
                addRemoveLinks:true,
                dictRemoveFileConfirmation:"{{trans('texts.are_you_sure')}}",
                @foreach(['default_message', 'fallback_message', 'fallback_text', 'file_too_big', 'invalid_file_type', 'response_error', 'cancel_upload', 'cancel_upload_confirmation', 'remove_file'] as $key)
                    "dict{{ Utils::toClassCase($key) }}" : "{!! strip_tags(addslashes(trans('texts.dropzone_'.$key))) !!}",
                @endforeach
                maxFilesize:{{floatval(MAX_DOCUMENT_SIZE/1000)}},
				parallelUploads:1,
            });
            if(dropzone instanceof Dropzone){
                dropzone.on("addedfile",handleDocumentAdded);
                dropzone.on("removedfile",handleDocumentRemoved);
                dropzone.on("success",handleDocumentUploaded);
                dropzone.on("canceled",handleDocumentCanceled);
                dropzone.on("error",handleDocumentError);
                for (var i=0; i<model.invoice().documents().length; i++) {
                    var document = model.invoice().documents()[i];
                    var mockFile = {
                        name:document.name(),
                        size:document.size(),
                        type:document.type(),
                        public_id:document.public_id(),
                        status:Dropzone.SUCCESS,
                        accepted:true,
                        url:document.url(),
                        mock:true,
                        index:i
                    };

                    dropzone.emit('addedfile', mockFile);
                    dropzone.emit('complete', mockFile);
                    if(document.preview_url()){
                        dropzone.emit('thumbnail', mockFile, document.preview_url());
                    }
                    else if(document.type()=='jpeg' || document.type()=='png' || document.type()=='svg'){
                        dropzone.emit('thumbnail', mockFile, document.url());
                    }
                    dropzone.files.push(mockFile);
                }
            }
        });
        @endif
	});

    function onFrequencyChange(){
        var currentName = $('#frequency_id').find('option:selected').text()
        var currentDueDateNumber = $('#recurring_due_date').find('option:selected').attr('data-num');
        var optionClass = currentName && currentName.toLowerCase().indexOf('week') > -1 ? 'weekly' :  'monthly';
        var replacementOption = $('#recurring_due_date option[data-num=' + currentDueDateNumber + '].' + optionClass);

        $('#recurring_due_date option').hide();
        $('#recurring_due_date option.' + optionClass).show();

        // Switch to an equivalent option
        if(replacementOption.length){
            replacementOption.attr('selected','selected');
        }
        else{
            $('#recurring_due_date').val('');
        }
    }

	function applyComboboxListeners() {
        var selectorStr = '.invoice-table input, .invoice-table textarea';
		$(selectorStr).off('change').on('change', function(event) {
            if ($(event.target).hasClass('handled')) {
                return;
            }
            $('#downloadPdfButton').attr('disabled', true);
            onItemChange();
            refreshPDF(true);
		});

        var selectorStr = '.invoice-table select';
        $(selectorStr).off('blur').on('blur', function(event) {
            onItemChange();
            refreshPDF(true);
        });

        $('textarea.word-wrap').on('keyup focus', function(e) {
            $(this).height(0).height(this.scrollHeight-18);
        });

	}

	function createInvoiceModel() {
        var model = ko.toJS(window.model);
        if(!model)return;
		var invoice = model.invoice;
		invoice.features = {
            customize_invoice_design:{{ Auth::user()->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN) ? 'true' : 'false' }},
            remove_created_by:{{ Auth::user()->hasFeature(FEATURE_REMOVE_CREATED_BY) ? 'true' : 'false' }},
            invoice_settings:{{ Auth::user()->hasFeature(FEATURE_INVOICE_SETTINGS) ? 'true' : 'false' }}
        };
		invoice.is_quote = {{ $entityType == ENTITY_QUOTE ? 'true' : 'false' }};
		invoice.contact = _.findWhere(invoice.client.contacts, {send_invoice: true});

        if (invoice.is_recurring) {
            invoice.invoice_number = "{{ trans('texts.assigned_when_sent') }}";
            if (invoice.start_date) {
                invoice.invoice_date = invoice.start_date;
            }
        }

        @if (!$invoice->id || $invoice->is_recurring)
            if (!invoice.terms) {
                invoice.terms = account['{{ $entityType }}_terms'];
            }
            if (!invoice.invoice_footer) {
                invoice.invoice_footer = account['invoice_footer'];
            }
        @endif

		@if ($account->hasLogo())
			invoice.image = "{{ Form::image_data($account->getLogoRaw(), true) }}";
			invoice.imageWidth = {{ $account->getLogoWidth() }};
			invoice.imageHeight = {{ $account->getLogoHeight() }};
		@endif

        //invoiceLabels.item = invoice.has_tasks ? invoiceLabels.date : invoiceLabels.item_orig;
        invoiceLabels.quantity = invoice.has_tasks ? invoiceLabels.hours : invoiceLabels.quantity_orig;
        invoiceLabels.unit_cost = invoice.has_tasks ? invoiceLabels.rate : invoiceLabels.unit_cost_orig;

        return invoice;
	}

	function getPDFString(cb, force) {
		@if ( ! $account->live_preview)
			return;
		@endif
        var invoice = createInvoiceModel();
		var design  = getDesignJavascript();
		if (!design) return;
        generatePDF(invoice, design, force, cb);
	}

	function getDesignJavascript() {
		var id = $('#invoice_design_id').val();
		if (id == '-1') {
			showMoreDesigns();
			model.invoice().invoice_design_id(1);
			return invoiceDesigns[0].javascript;
		} else {
            var design = _.find(invoiceDesigns, function(design){ return design.id == id});
            return design ? design.javascript : '';
		}
	}

    function resetTerms() {
        sweetConfirm(function() {
            model.invoice().terms(model.invoice().default_terms());
            refreshPDF();
        });

        return false;
    }

    function resetFooter() {
        sweetConfirm(function() {
            model.invoice().invoice_footer(model.invoice().default_footer());
            refreshPDF();
        });

        return false;
    }

	function onDownloadClick() {
		trackEvent('/activity', '/download_pdf');
		var invoice = createInvoiceModel();
        var design  = getDesignJavascript();
		if (!design) return;
		var doc = generatePDF(invoice, design, true);
        var type = invoice.is_quote ? '{!! trans('texts.'.ENTITY_QUOTE) !!}' : '{!! trans('texts.'.ENTITY_INVOICE) !!}';
		doc.save(type + '-' + $('#invoice_number').val() + '.pdf');
	}

    function onRecurrClick() {
        var invoice = model.invoice();
        if (invoice.is_recurring()) {
            var recurring = false;
            var enableLabel = "{{ trans('texts.enable_recurring')}}";
			var actionLabel = "{{ trans('texts.mark_sent') }}";
        } else {
            var recurring = true;
            var enableLabel = "{{ trans('texts.disable_recurring')}}";
			var actionLabel = "{{ trans('texts.mark_active') }}";
        }
        invoice.is_recurring(recurring);
        $('#recurrButton').html(enableLabel + "<span class='glyphicon glyphicon-repeat'></span>");
		$('#saveButton').html(actionLabel + "<span class='glyphicon glyphicon-globe'></span>");
    }

	function onEmailClick() {
        if (!NINJA.isRegistered) {
            swal("{!! trans('texts.registration_required') !!}");
            return;
        }

        var clientId = parseInt($('input[name=client]').val(), 10) || 0;
        if (clientId == 0 ) {
            swal("{!! trans('texts.no_client_selected') !!}");
            return;
        }

        if (!isContactSelected()) {
            swal("{!! trans('texts.no_contact_selected') !!}");
            return;
        }

        if (!isEmailValid()) {
            swal("{!! trans('texts.provide_email') !!}");
            return;
        }

		if (model.invoice().is_recurring()) {
			sweetConfirm(function() {
				onConfirmEmailClick();
			}, getSendToEmails());
		} else {
			showEmailModal();
		}
	}

	function onConfirmEmailClick() {
		$('#emailModal div.modal-footer button').attr('disabled', true);
		model.invoice().is_public(true);
		submitAction('email');
	}

	function onSaveDraftClick() {
		model.invoice().is_public(false);
		onSaveClick();
	}

	function onMarkSentClick() {
		if (model.invoice().is_recurring()) {
			if (! model.invoice().start_date()) {
				swal("{{ trans('texts.start_date_required') }}");
				return false;
			}
			if (!isSaveValid()) {
	            model.showClientForm();
	            return false;
	        }
            // warn invoice will be emailed when saving new recurring invoice
            var text = '\n' + getSendToEmails();
			if (model.invoice().start_date() == "{{ Utils::fromSqlDate(date('Y-m-d')) }}") {
				text += '\n\n' + "{!! trans("texts.confirm_recurring_timing") !!}";
			}
            var title = "{!! trans("texts.confirm_recurring_email_$entityType") !!}";
            sweetConfirm(function() {
				model.invoice().is_public(true);
                submitAction('');
            }, text, title);
            return;
        } else {
			model.invoice().is_public(true);
			onSaveClick();
		}
	}

	function onSaveClick() {
		@if ($invoice->id)
			if (model.invoice().is_recurring()) {
	            if (model.invoice().start_date() != model.invoice().start_date_orig()) {
	                var text = "{!! trans("texts.original_start_date") !!}: " + model.invoice().start_date_orig() + '\n'
	                            + "{!! trans("texts.new_start_date") !!}: " + model.invoice().start_date();
	                var title = "{!! trans("texts.warn_start_date_changed") !!}";
	                sweetConfirm(function() {
	                    submitAction('');
	                }, text, title);
	                return;
	            }
	        }
		@endif

        @if (!empty($autoBillChangeWarning))
            var text = "{!! trans('texts.warn_change_auto_bill') !!}";
            sweetConfirm(function() {
                submitAction('');
            }, text);
            return;
        @endif

        submitAction('');
    }

    function getSendToEmails() {
        var client = model.invoice().client();
        var parts = [];

        for (var i=0; i<client.contacts().length; i++) {
            var contact = client.contacts()[i];
            if (contact.send_invoice()) {
                parts.push(contact.displayName());
            }
        }

        return parts.join('\n');
    }

    function preparePdfData(action) {
        var invoice = createInvoiceModel();
        var design = getDesignJavascript();
        if (!design) return;

        doc = generatePDF(invoice, design, true);
        doc.getDataUrl( function(pdfString){
            $('#pdfupload').val(pdfString);
            submitAction(action);
        });
    }

	function submitAction(value) {
		if (!isSaveValid()) {
            model.showClientForm();
            return false;
        }

		$('#action').val(value);
		$('#submitButton').click();
	}

    function onFormSubmit(event) {
        if (window.countUploadingDocuments > 0) {
            swal("{!! trans('texts.wait_for_upload') !!}");
            return false;
        }

        @if ($invoice->is_deleted)
            if ($('#bulk_action').val() != 'restore') {
                return false;
            }
        @endif

        // check invoice number is unique
        if ($('.invoice-number').hasClass('has-error')) {
            return false;
        } else if ($('.partial').hasClass('has-error')) {
            return false;
        }

        if (!isSaveValid()) {
            model.showClientForm();
            return false;
        }

        // check currency matches for expenses
        var expenseCurrencyId = model.expense_currency_id();
        var clientCurrencyId = model.invoice().client().currency_id() || {{ $account->getCurrencyId() }};
        if (expenseCurrencyId && expenseCurrencyId != clientCurrencyId) {
            swal("{!! trans('texts.expense_error_mismatch_currencies') !!}");
            return false;
        }

        @if (Auth::user()->canCreateOrEdit(ENTITY_INVOICE, $invoice))
            if ($('#saveButton').is(':disabled')) {
                return false;
            }
            $('#saveButton, #emailButton, #draftButton').attr('disabled', true);
            // if save fails ensure user can try again
            $.post('{{ url($url) }}', $('.main-form').serialize(), function(data) {
				if (data && data.indexOf('http') === 0) {
					NINJA.formIsChanged = false;
					location.href = data;
				} else {
					handleSaveFailed();
				}
            }).fail(function(data) {
				handleSaveFailed(data);
            });
            return false;
        @else
            return false;
        @endif
    }

	function handleSaveFailed(data) {
		$('#saveButton, #emailButton, #draftButton').attr('disabled', false);
		$('#emailModal div.modal-footer button').attr('disabled', false);
		var error = '';
		if (data) {
			var error = firstJSONError(data.responseJSON) || data.statusText;
		}
		swal("{!! trans('texts.invoice_save_error') !!}", error);
	}

    function submitBulkAction(value) {
        $('#bulk_action').val(value);
        $('.bulkForm')[0].submit();
    }

	function isSaveValid() {
		var isValid = model.invoice().client().name() ? true : false;
		for (var i=0; i<model.invoice().client().contacts().length; i++) {
			var contact = model.invoice().client().contacts()[i];
			if (isValidEmailAddress(contact.email()) || contact.first_name() || contact.last_name()) {
				isValid = true;
				break;
			}
		}
		return isValid;
	}

    function isContactSelected() {
		var sendTo = false;
		var client = model.invoice().client();
		for (var i=0; i<client.contacts().length; i++) {
			var contact = client.contacts()[i];
            if (contact.send_invoice()) {
                return true;
            }
		}
		return false;
    }

	function isEmailValid() {
		var isValid = true;
		var client = model.invoice().client();
		for (var i=0; i<client.contacts().length; i++) {
			var contact = client.contacts()[i];
            if ( ! contact.send_invoice()) {
                continue;
            }
			if (isValidEmailAddress(contact.email())) {
				isValid = true;
			} else {
				isValid = false;
				break;
			}
		}
		return isValid;
	}

	function onCloneClick() {
		submitAction('clone');
	}

	function onConvertClick() {
		submitAction('convert');
	}

    @if ($invoice->id)
    	function onPaymentClick() {
            @if (!empty($autoBillChangeWarning))
                sweetConfirm(function() {
                    window.location = '{{ URL::to('payments/create/' . $invoice->client->public_id . '/' . $invoice->public_id ) }}';
                }, "{!! trans('texts.warn_change_auto_bill') !!}");
            @else
                window.location = '{{ URL::to('payments/create/' . $invoice->client->public_id . '/' . $invoice->public_id ) }}';
            @endif
    	}

    	function onCreditClick() {
    		window.location = '{{ URL::to('credits/create/' . $invoice->client->public_id . '/' . $invoice->public_id ) }}';
    	}
    @endif

	function onArchiveClick() {
		submitBulkAction('archive');
	}

	function onDeleteClick() {
        sweetConfirm(function() {
            submitBulkAction('delete');
        });
	}

	function formEnterClick(event) {
		if (event.keyCode === 13){
			if (event.target.type == 'textarea') {
				return;
			}
			event.preventDefault();

            @if($invoice->trashed())
                return;
            @endif
			submitAction('');
			return false;
		}
	}

	function clientModalEnterClick(event) {
		if (event.keyCode === 13){
			event.preventDefault();
            model.clientFormComplete();
            return false;
        }
	}

	function onItemChange(silent)
	{
		var hasEmpty = false;
		for(var i=0; i<model.invoice().invoice_items().length; i++) {
			var item = model.invoice().invoice_items()[i];
			if (item.isEmpty()) {
				hasEmpty = true;
			}
		}

		if (!hasEmpty) {
			model.invoice().addItem();
		}

		if (!silent) {
        	NINJA.formIsChanged = true;
		}
	}

    function onPartialChange()
    {
        var val = NINJA.parseFloat($('#partial').val());
        var oldVal = val;
        val = Math.max(Math.min(val, model.invoice().totals.rawTotal()), 0);

        if (val != oldVal) {
            if ($('.partial').hasClass('has-error')) {
                return;
            }
            $('.partial')
                .addClass('has-error')
                .find('div')
                .append('<span class="help-block">{{ trans('texts.partial_value') }}</span>');
        } else {
            $('.partial')
                .removeClass('has-error')
                .find('span')
                .hide();
        }

    }

    function onRecurringEnabled()
    {
        if ($('#recurring').prop('checked')) {
            $('#emailButton').attr('disabled', true);
            model.invoice().partial('');
        } else {
            $('#emailButton').removeAttr('disabled');
        }
    }

    function showLearnMore() {
        $('#recurringModal').modal('show');
    }

    function showRecurringDueDateLearnMore() {
        $('#recurringDueDateModal').modal('show');
    }

    function setInvoiceNumber(client) {
		@if ($invoice->id || !$account->hasClientNumberPattern($invoice))
            return;
        @endif
        var number = '{{ $account->applyNumberPattern($invoice) }}';
        number = number.replace('{$clientCustom1}', client.custom_value1 ? client.custom_value1 : '');
        number = number.replace('{$clientCustom2}', client.custom_value2 ? client.custom_value1 : '');
        number = number.replace('{$clientIdNumber}', client.id_number ? client.id_number : '');
		@if ($invoice->isQuote() && ! $account->share_counter)
			number = number.replace('{$clientCounter}', pad(client.quote_number_counter, {{ $account->invoice_number_padding }}));
		@else
        	number = number.replace('{$clientCounter}', pad(client.invoice_number_counter, {{ $account->invoice_number_padding }}));
		@endif
		// backwards compatibility
		number = number.replace('{$custom1}', client.custom_value1 ? client.custom_value1 : '');
        number = number.replace('{$custom2}', client.custom_value2 ? client.custom_value1 : '');
        number = number.replace('{$idNumber}', client.id_number ? client.id_number : '');
        model.invoice().invoice_number(number);
    }

    window.countUploadingDocuments = 0;

    function handleDocumentAdded(file){
        // open document when clicked
        if (file.url) {
            file.previewElement.addEventListener("click", function() {
                window.open(file.url, '_blank');
            });
        }
        if(file.mock)return;
        file.index = model.invoice().documents().length;
        model.invoice().addDocument({name:file.name, size:file.size, type:file.type});
        window.countUploadingDocuments++;
    }

    function handleDocumentRemoved(file){
        model.invoice().removeDocument(file.public_id);
        refreshPDF(true);
        $.ajax({
            url: '{{ '/documents/' }}' + file.public_id,
            type: 'DELETE',
            success: function(result) {
                // Do something with the result
            }
        });
    }

    function handleDocumentUploaded(file, response){
        window.countUploadingDocuments--;
        file.public_id = response.document.public_id
        model.invoice().documents()[file.index].update(response.document);
        @if ($account->invoice_embed_documents)
            refreshPDF(true);
        @endif
        if(response.document.preview_url){
            dropzone.emit('thumbnail', file, response.document.preview_url);
        }
    }

    function handleDocumentCanceled() {
        window.countUploadingDocuments--;
    }

    function handleDocumentError(file) {
		dropzone.removeFile(file);
        window.countUploadingDocuments--;
		swal("{!! trans('texts.error_refresh_page') !!}");
    }

	</script>
    @if ($account->hasFeature(FEATURE_DOCUMENTS) && $account->invoice_embed_documents)
        @foreach ($invoice->documents as $document)
            @if($document->isPDFEmbeddable())
                <script src="{{ $document->getVFSJSUrl() }}" type="text/javascript" async></script>
            @endif
        @endforeach
        @foreach ($invoice->expenses as $expense)
            @foreach ($expense->documents as $document)
                @if($document->isPDFEmbeddable())
                    <script src="{{ $document->getVFSJSUrl() }}" type="text/javascript" async></script>
                @endif
            @endforeach
        @endforeach
    @endif

@stop
