@extends('header')

@section('head')
	@parent

		<script src="{{ asset('js/pdf_viewer.js') }}" type="text/javascript"></script>
		<script src="{{ asset('js/compatibility.js') }}" type="text/javascript"></script>
        <script src="{{ asset('js/pdfmake.min.js') }}" type="text/javascript"></script>
        <script src="{{ asset('js/vfs_fonts.js') }}" type="text/javascript"></script>    

@stop

@section('content')
	
	@if ($invoice && $invoice->id)
		<ol class="breadcrumb">
            @if ($isRecurring)
             <li>{!! link_to('invoices', trans('texts.recurring_invoice')) !!}</li>
            @else
			 <li>{!! link_to(($entityType == ENTITY_QUOTE ? 'quotes' : 'invoices'), trans('texts.' . ($entityType == ENTITY_QUOTE ? 'quotes' : 'invoices'))) !!}</li>
			 <li class='active'>{{ $invoice->invoice_number }}</li>
            @endif
		</ol>  
	@endif

	{!! Former::open($url)->method($method)->addClass('warn-on-exit')->rules(array(
		'client' => 'required',
		'product_key' => 'max:255'
	)) !!}	


	<input type="submit" style="display:none" name="submitButton" id="submitButton">

	<div data-bind="with: invoice">
    <div class="panel panel-default">
    <div class="panel-body" style="padding-bottom: 0px;">

    <div class="row" style="min-height:195px" onkeypress="formEnterClick(event)">
    	<div class="col-md-4" id="col_1">

    		@if ($invoice && $invoice->id)
				<div class="form-group">
					<label for="client" class="control-label col-lg-4 col-sm-4">Client</label>
					<div class="col-lg-8 col-sm-8">
                        <h4><div data-bind="text: getClientDisplayName(ko.toJS(client()))"></div></h4>
						<a id="editClientLink" class="pointer" data-bind="click: $root.showClientForm">{{ trans('texts.edit_client') }}</a> |
                        {!! link_to('/clients/'.$invoice->client->public_id, trans('texts.view_client'), ['target' => '_blank']) !!}
					</div>
				</div>    				
				<div style="display:none">
    		@endif

			{!! Former::select('client')->addOption('', '')->data_bind("dropdown: client")->addClass('client-input')->addGroupClass('client_select closer-row') !!}

			<div class="form-group" style="margin-bottom: 8px">
				<div class="col-lg-8 col-sm-8 col-lg-offset-4 col-sm-offset-4">
					<a id="createClientLink" class="pointer" data-bind="click: $root.showClientForm, html: $root.clientLinkText"></a>
                    <span data-bind="visible: $root.invoice().client().public_id() > 0">| 
                        <a data-bind="attr: {href: '{{ url('/clients') }}/' + $root.invoice().client().public_id()}" target="_blank">{{ trans('texts.view_client') }}</a>
                    </span>
				</div>
			</div>

			@if ($invoice && $invoice->id)
				</div>
			@endif

			<div data-bind="with: client">
				<div style="display:none" class="form-group" data-bind="visible: contacts().length > 0 &amp;&amp; (contacts()[0].email() || contacts()[0].first_name()), foreach: contacts">
					<div class="col-lg-8 col-lg-offset-4">
						<label class="checkbox" data-bind="attr: {for: $index() + '_check'}" onclick="refreshPDF(true)">
							<input type="checkbox" value="1" data-bind="checked: send_invoice, attr: {id: $index() + '_check'}">
								<span data-bind="html: email.display"/>
						</label>
					</div>				
				</div>
			</div>
			
		</div>
		<div class="col-md-4" id="col_2">
			<div data-bind="visible: !is_recurring()">
				{!! Former::text('invoice_date')->data_bind("datePicker: invoice_date, valueUpdate: 'afterkeydown'")->label(trans("texts.{$entityType}_date"))
							->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))->appendIcon('calendar')->addGroupClass('invoice_date') !!}
				{!! Former::text('due_date')->data_bind("datePicker: due_date, valueUpdate: 'afterkeydown'")->label(trans("texts.{$entityType}_due_date"))
							->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))->appendIcon('calendar')->addGroupClass('due_date') !!}
                
                {!! Former::text('partial')->data_bind("value: partial, valueUpdate: 'afterkeydown'")->onchange('onPartialChange()')
                            ->rel('tooltip')->data_toggle('tooltip')->data_placement('bottom')->title(trans('texts.partial_value')) !!}
			</div>
			@if ($entityType == ENTITY_INVOICE)
				<div data-bind="visible: is_recurring" style="display: none">
					{!! Former::select('frequency_id')->options($frequencies)->data_bind("value: frequency_id")
                            ->appendIcon('question-sign')->addGroupClass('frequency_id') !!}
					{!! Former::text('start_date')->data_bind("datePicker: start_date, valueUpdate: 'afterkeydown'")
								->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))->appendIcon('calendar')->addGroupClass('start_date') !!}
					{!! Former::text('end_date')->data_bind("datePicker: end_date, valueUpdate: 'afterkeydown'")
								->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT, DEFAULT_DATE_PICKER_FORMAT))->appendIcon('calendar')->addGroupClass('end_date') !!}
				</div>
				@if ($invoice && $invoice->recurring_invoice)
					<div class="pull-right" style="padding-top: 6px">
                        {!! trans('texts.created_by_invoice', ['invoice' => link_to('/invoices/'.$invoice->recurring_invoice->public_id, trans('texts.recurring_invoice'))]) !!}
					</div>
				@elseif ($invoice && isset($lastSent) && $lastSent)
                    <div class="pull-right" style="padding-top: 6px">
                        {!! trans('texts.last_sent_on', ['date' => link_to('/invoices/'.$lastSent->public_id, Utils::dateToString($invoice->last_sent_date))]) !!}
                    </div>
                @endif
			@endif
			
		</div>

		<div class="col-md-4" id="col_2">
            <span data-bind="visible: !is_recurring()">
            {!! Former::text('invoice_number')
                        ->label(trans("texts.{$entityType}_number_short"))
                        ->data_bind("value: invoice_number, valueUpdate: 'afterkeydown'") !!}
            </span>            
            <span data-bind="visible: is_recurring()" style="display: none">
            {!! Former::checkbox('auto_bill')
                        ->label(trans('texts.auto_bill'))
                        ->text(trans('texts.enable'))
                        ->data_bind("checked: auto_bill, valueUpdate: 'afterkeydown'") !!}
            </span>
			{!! Former::text('po_number')->label(trans('texts.po_number_short'))->data_bind("value: po_number, valueUpdate: 'afterkeydown'") !!}
			{!! Former::text('discount')->data_bind("value: discount, valueUpdate: 'afterkeydown'")
					->addGroupClass('discount-group')->type('number')->min('0')->step('any')->append(
						Former::select('is_amount_discount')->addOption(trans('texts.discount_percent'), '0')
						->addOption(trans('texts.discount_amount'), '1')->data_bind("value: is_amount_discount")->raw()
			) !!}			
			
			<div class="form-group" style="margin-bottom: 8px">
				<label for="taxes" class="control-label col-lg-4 col-sm-4">{{ trans('texts.taxes') }}</label>
				<div class="col-lg-8 col-sm-8" style="padding-top: 10px">
					<a href="#" data-bind="click: $root.showTaxesForm"><i class="glyphicon glyphicon-list-alt"></i> {{ trans('texts.manage_rates') }}</a>
				</div>
			</div>

		</div>
	</div>

	<p>&nbsp;</p>

	<div class="table-responsive">
	<table class="table invoice-table">
		<thead>
			<tr>
				<th style="min-width:32px;" class="hide-border"></th>
				<th style="min-width:160px" data-bind="text: productLabel"></th>
				<th style="width:100%">{{ $invoiceLabels['description'] }}</th>
				<th style="min-width:120px" data-bind="text: costLabel"></th>
				<th style="{{ $account->hide_quantity ? 'display:none' : 'min-width:120px' }}" data-bind="text: qtyLabel"></th>
				<th style="min-width:120px;display:none;" data-bind="visible: $root.invoice_item_taxes.show">{{ trans('texts.tax') }}</th>
				<th style="min-width:120px;">{{ trans('texts.line_total') }}</th>
				<th style="min-width:32px;" class="hide-border"></th>
			</tr>
		</thead>
		<tbody data-bind="sortable: { data: invoice_items, afterMove: onDragged }">
			<tr data-bind="event: { mouseover: showActions, mouseout: hideActions }" class="sortable-row">
				<td class="hide-border td-icon">
					<i style="display:none" data-bind="visible: actionsVisible() &amp;&amp;
                        $index() < ($parent.invoice_items().length - 1) &amp;&amp;
                        $parent.invoice_items().length > 1" class="fa fa-sort"></i>
				</td>
				<td>
                {!! Former::text('product_key')->useDatalist($products->toArray(), 'product_key')->onkeyup('onItemChange()')
				       ->raw()->data_bind("value: product_key, valueUpdate: 'afterkeydown'")->addClass('datalist') !!}
				</td>
				<td>
					<textarea data-bind="value: wrapped_notes, valueUpdate: 'afterkeydown'" rows="1" cols="60" style="resize: vertical" class="form-control word-wrap"></textarea>
				</td>
				<td>
					<input onkeyup="onItemChange()" data-bind="value: prettyCost, valueUpdate: 'afterkeydown'" style="text-align: right" class="form-control"//>
				</td>
				<td style="{{ $account->hide_quantity ? 'display:none' : '' }}">
					<input onkeyup="onItemChange()" data-bind="value: prettyQty, valueUpdate: 'afterkeydown'" style="text-align: right" class="form-control"//>
				</td>
				<td style="display:none;" data-bind="visible: $root.invoice_item_taxes.show">
					<select class="form-control" style="width:100%" data-bind="value: tax, options: $root.tax_rates, optionsText: 'displayName'"></select>
				</td>
				<td style="text-align:right;padding-top:9px !important">
					<div class="line-total" data-bind="text: totals.total"></div>
				</td>
				<td style="cursor:pointer" class="hide-border td-icon">
                    <i style="display:none;padding-left:4px" data-bind="click: $parent.removeItem, visible: actionsVisible() &amp;&amp; 
                    $index() < ($parent.invoice_items().length - 1) &amp;&amp;
                    $parent.invoice_items().length > 1" class="fa fa-minus-circle redlink" title="Remove item"/>
				</td>
			</tr>
		</tbody>


		<tfoot>
			<tr>
				<td class="hide-border"/>
				<td class="hide-border" colspan="2" rowspan="6" style="vertical-align:top">
					<br/>
                    <div role="tabpanel">

                      <ul class="nav nav-tabs" role="tablist" style="border: none">
                        <li role="presentation" class="active"><a href="#notes" aria-controls="notes" role="tab" data-toggle="tab">{{ trans('texts.note_to_client') }}</a></li>
                        <li role="presentation"><a href="#terms" aria-controls="terms" role="tab" data-toggle="tab">{{ trans('texts.invoice_terms') }}</a></li>
                        <li role="presentation"><a href="#footer" aria-controls="footer" role="tab" data-toggle="tab">{{ trans('texts.invoice_footer') }}</a></li>
                    </ul>

                    <div class="tab-content">
                        <div role="tabpanel" class="tab-pane active" id="notes" style="padding-bottom:44px">
                            {!! Former::textarea('public_notes')->data_bind("value: wrapped_notes, valueUpdate: 'afterkeydown'")
                            ->label(null)->style('resize: none; min-width: 450px;')->rows(3) !!}                            
                        </div>
                        <div role="tabpanel" class="tab-pane" id="terms">
                            {!! Former::textarea('terms')->data_bind("value:wrapped_terms, placeholder: terms_placeholder, valueUpdate: 'afterkeydown'")
                            ->label(false)->style('resize: none; min-width: 450px')->rows(3)
                            ->help('<div class="checkbox">
                                        <label>
                                            <input type="checkbox" style="width: 24px" data-bind="checked: set_default_terms"/>'.trans('texts.save_as_default_terms').'
                                        </label>
                                        <div class="pull-right"><a href="#" onclick="return resetTerms()">' . trans("texts.reset_terms") . '</a></div>
                                    </div>') !!}
                        </div>
                        <div role="tabpanel" class="tab-pane" id="footer">
                            {!! Former::textarea('invoice_footer')->data_bind("value:wrapped_footer, placeholder: footer_placeholder, valueUpdate: 'afterkeydown'")
                            ->label(false)->style('resize: none; min-width: 450px')->rows(3)
                            ->help('<div class="checkbox">
                                        <label>
                                            <input type="checkbox" style="width: 24px" data-bind="checked: set_default_footer"/>'.trans('texts.save_as_default_footer').'
                                        </label>
                                        <div class="pull-right"><a href="#" onclick="return resetFooter()">' . trans("texts.reset_footer") . '</a></div>
                                    </div>') !!}
                        </div>
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

			@if (($account->custom_invoice_label1 || ($invoice && floatval($invoice->custom_value1)) != 0) && $account->custom_invoice_taxes1)
				<tr>
					<td class="hide-border" colspan="3"/>
					<td style="display:none" class="hide-border" data-bind="visible: $root.invoice_item_taxes.show"/>
					<td colspan="{{ $account->hide_quantity ? 1 : 2 }}">{{ $account->custom_invoice_label1 }}</td>
					<td style="text-align: right;padding-right: 28px" colspan="2"><input class="form-control" data-bind="value: custom_value1, valueUpdate: 'afterkeydown'"/></td>
				</tr>
			@endif

			@if (($account->custom_invoice_label2 || ($invoice && floatval($invoice->custom_value2)) != 0) && $account->custom_invoice_taxes2)
				<tr>
					<td class="hide-border" colspan="3"/>
					<td style="display:none" class="hide-border" data-bind="visible: $root.invoice_item_taxes.show"/>
					<td colspan="{{ $account->hide_quantity ? 1 : 2 }}">{{ $account->custom_invoice_label2 }}</td>
					<td style="text-align: right;padding-right: 28px" colspan="2"><input class="form-control" data-bind="value: custom_value2, valueUpdate: 'afterkeydown'"/></td>
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
				<td style="min-width:120px"><select class="form-control" style="width:100%" data-bind="value: tax, options: $root.tax_rates, optionsText: 'displayName'"></select></td>
				<td style="text-align: right"><span data-bind="text: totals.taxAmount"/></td>
			</tr>

			@if (($account->custom_invoice_label1 || ($invoice && floatval($invoice->custom_value1)) != 0) && !$account->custom_invoice_taxes1)
				<tr>
					<td class="hide-border" colspan="3"/>
					<td style="display:none" class="hide-border" data-bind="visible: $root.invoice_item_taxes.show"/>
					<td colspan="{{ $account->hide_quantity ? 1 : 2 }}">{{ $account->custom_invoice_label1 }}</td>
					<td style="text-align: right;padding-right: 28px" colspan="2"><input class="form-control" data-bind="value: custom_value1, valueUpdate: 'afterkeydown'"/></td>
				</tr>
			@endif

			@if (($account->custom_invoice_label2 || ($invoice && floatval($invoice->custom_value2)) != 0) && !$account->custom_invoice_taxes2)
				<tr>
					<td class="hide-border" colspan="3"/>
					<td style="display:none" class="hide-border" data-bind="visible: $root.invoice_item_taxes.show"/>
					<td colspan="{{ $account->hide_quantity ? 1 : 2 }}">{{ $account->custom_invoice_label2 }}</td>
					<td style="text-align: right;padding-right: 28px" colspan="2"><input class="form-control" data-bind="value: custom_value2, valueUpdate: 'afterkeydown'"/></td>
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

			<tr>
				<td class="hide-border" colspan="3"/>
				<td class="hide-border" style="display:none" data-bind="visible: $root.invoice_item_taxes.show"/>	        	
				<td class="hide-border" colspan="{{ $account->hide_quantity ? 1 : 2 }}"><b>{{ trans($entityType == ENTITY_INVOICE ? 'texts.balance_due' : 'texts.total') }}</b></td>
				<td class="hide-border" style="text-align: right"><span data-bind="text: totals.total"></span></td>
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
            {!! Former::text('data')->data_bind("value: ko.mapping.toJSON(model)") !!}
            {!! Former::text('pdfupload') !!}    
				
			@if ($invoice && $invoice->id)
				{!! Former::populateField('id', $invoice->public_id) !!}
				{!! Former::text('id') !!}
			@endif
		</div>


		@if (!Utils::isPro() || \App\Models\InvoiceDesign::count() == COUNT_FREE_DESIGNS_SELF_HOST)
			{!! Former::select('invoice_design_id')->style('display:inline;width:150px;background-color:white !important')->raw()->fromQuery($invoiceDesigns, 'name', 'id')->data_bind("value: invoice_design_id")->addOption(trans('texts.more_designs') . '...', '-1') !!}
		@else 
			{!! Former::select('invoice_design_id')->style('display:inline;width:150px;background-color:white !important')->raw()->fromQuery($invoiceDesigns, 'name', 'id')->data_bind("value: invoice_design_id") !!}
		@endif

		{!! Button::primary(trans('texts.download_pdf'))->withAttributes(array('onclick' => 'onDownloadClick()'))->appendIcon(Icon::create('download-alt')) !!}	
        
		@if (!$invoice || (!$invoice->trashed() && !$invoice->client->trashed()))

			{!! Button::success(trans("texts.save_{$entityType}"))->withAttributes(array('id' => 'saveButton', 'onclick' => 'onSaveClick()'))->appendIcon(Icon::create('floppy-disk')) !!}
		    {!! Button::info(trans("texts.email_{$entityType}"))->withAttributes(array('id' => 'emailButton', 'onclick' => 'onEmailClick()'))->appendIcon(Icon::create('send')) !!}

            @if ($invoice && $invoice->id)                
                {!! DropdownButton::normal(trans('texts.more_actions'))
                      ->withContents($actions)
                      ->dropup() !!}
            @endif            

		@elseif ($invoice && $invoice->trashed() && !$invoice->is_deleted == '1')
			{!! Button::success(trans('texts.restore'))->withAttributes(['onclick' => 'submitAction("restore")'])->appendIcon(Icon::create('cloud-download')) !!}
		@endif

	</div>
	<p>&nbsp;</p>

	@include('invoices.pdf', ['account' => Auth::user()->account])

	@if (!Auth::user()->account->isPro())
		<div style="font-size:larger">
			{!! trans('texts.pro_plan.remove_logo', ['link'=>'<a href="#" onclick="showProPlan(\'remove_logo\')">'.trans('texts.pro_plan.remove_logo_link').'</a>']) !!}
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

                {!! Former::text('name')->data_bind("value: name, valueUpdate: 'afterkeydown', attr { placeholder: name.placeholder }")->label('client_name') !!}
                <span data-bind="visible: $root.showMore">
                    {!! Former::text('id_number')->data_bind("value: id_number, valueUpdate: 'afterkeydown'") !!}
                    {!! Former::text('vat_number')->data_bind("value: vat_number, valueUpdate: 'afterkeydown'") !!}
                    
                    {!! Former::text('website')->data_bind("value: website, valueUpdate: 'afterkeydown'") !!}
                    {!! Former::text('work_phone')->data_bind("value: work_phone, valueUpdate: 'afterkeydown'") !!}

                    @if (Auth::user()->isPro())             
                        @if ($account->custom_client_label1)
                            {!! Former::text('custom_value1')->label($account->custom_client_label1)
                                ->data_bind("value: custom_value1, valueUpdate: 'afterkeydown'") !!}
                        @endif
                        @if ($account->custom_client_label2)
                            {!! Former::text('custom_value2')->label($account->custom_client_label2)
                                ->data_bind("value: custom_value2, valueUpdate: 'afterkeydown'") !!}
                        @endif
                    @endif              

                    &nbsp;

                    {!! Former::text('address1')->data_bind("value: address1, valueUpdate: 'afterkeydown'") !!}
                    {!! Former::text('address2')->data_bind("value: address2, valueUpdate: 'afterkeydown'") !!}
                    {!! Former::text('city')->data_bind("value: city, valueUpdate: 'afterkeydown'") !!}
                    {!! Former::text('state')->data_bind("value: state, valueUpdate: 'afterkeydown'") !!}
                    {!! Former::text('postal_code')->data_bind("value: postal_code, valueUpdate: 'afterkeydown'") !!}
                    {!! Former::select('country_id')->addOption('','')->addGroupClass('country_select')
                        ->fromQuery($countries, 'name', 'id')->data_bind("dropdown: country_id") !!}
                </span>

            </div>
            <div style="margin-left:0px;margin-right:0px" data-bind="css: {'col-md-6': $root.showMore}">

                <div data-bind='template: { foreach: contacts,
                                        beforeRemove: hideContact,
                                        afterAdd: showContact }'>
                    {!! Former::hidden('public_id')->data_bind("value: public_id, valueUpdate: 'afterkeydown'") !!}
                    {!! Former::text('first_name')->data_bind("value: first_name, valueUpdate: 'afterkeydown'") !!}
                    {!! Former::text('last_name')->data_bind("value: last_name, valueUpdate: 'afterkeydown'") !!}
                    {!! Former::text('email')->data_bind('value: email, valueUpdate: \'afterkeydown\', attr: {id:\'email\'+$index()}') !!}                    
                    {!! Former::text('phone')->data_bind("value: phone, valueUpdate: 'afterkeydown'") !!}                    
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

                {!! Former::select('currency_id')->addOption('','')
                    ->placeholder($account->currency ? $account->currency->name : '')
                    ->data_bind('value: currency_id')
                    ->fromQuery($currencies, 'name', 'id') !!}

                <span data-bind="visible: $root.showMore">
                {!! Former::select('language_id')->addOption('','')
                    ->placeholder($account->language ? $account->language->name : '')
                    ->data_bind('value: language_id')
                    ->fromQuery($languages, 'name', 'id') !!}                
                {!! Former::select('payment_terms')->addOption('','')->data_bind('value: payment_terms')
                    ->fromQuery($paymentTerms, 'name', 'num_days')
                    ->help(trans('texts.payment_terms_help')) !!}
                {!! Former::select('size_id')->addOption('','')->data_bind('value: size_id')
                    ->fromQuery($sizes, 'name', 'id') !!}
                {!! Former::select('industry_id')->addOption('','')->data_bind('value: industry_id')
                    ->fromQuery($industries, 'name', 'id') !!}
                {!! Former::textarea('private_notes')->data_bind('value: private_notes') !!}
                </span>
            </div>
            </div>
        </div>
        </div>
        </div>

         <div class="modal-footer" style="margin-top: 0px; padding-top:0px;">
            <span class="error-block" id="emailError" style="display:none;float:left;font-weight:bold">{{ trans('texts.provide_name_or_email') }}</span><span>&nbsp;</span>
            <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.cancel') }}</button>
            <button type="button" class="btn btn-default" data-bind="click: $root.showMoreFields, text: $root.showMore() ? '{{ trans('texts.less_fields') }}' : '{{ trans('texts.more_fields') }}'"></button>
            <button id="clientDoneButton" type="button" class="btn btn-primary" data-bind="click: $root.clientFormComplete">{{ trans('texts.done') }}</button>          
         </div>
            
        </div>
      </div>
    </div>

	<div class="modal fade" id="taxModal" tabindex="-1" role="dialog" aria-labelledby="taxModalLabel" aria-hidden="true">
	  <div class="modal-dialog">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	        <h4 class="modal-title" id="taxModalLabel">{{ trans('texts.tax_rates') }}</h4>
	      </div>

	      <div style="background-color: #fff" onkeypress="taxModalEnterClick(event)">
			<table class="table invoice-table sides-padded" style="margin-bottom: 0px !important">
			    <thead>
			        <tr>
			        	<th class="hide-border"></th>
			        	<th class="hide-border">{{ trans('texts.name') }}</th>
			        	<th class="hide-border">{{ trans('texts.rate') }}</th>
			        	<th class="hide-border"></th>
			        </tr>
			    </thead>
			    <tbody data-bind="foreach: $root.tax_rates.filtered">
			    	<tr data-bind="event: { mouseover: showActions, mouseout: hideActions }">
			    		<td style="width:30px" class="hide-border"></td>
			            <td style="width:60px">
			            	<input onkeyup="onTaxRateChange()" data-bind="value: name, valueUpdate: 'afterkeydown'" class="form-control" onchange="refreshPDF(true)"//>
			            </td>
			            <td style="width:60px">
			            	<input onkeyup="onTaxRateChange()" data-bind="value: prettyRate, valueUpdate: 'afterkeydown'" style="text-align: right" class="form-control" onchange="refreshPDF(true)"//>
			            </td>
			        	<td style="width:30px; cursor:pointer" class="hide-border td-icon">
			        		&nbsp;<i style="width:12px;" data-bind="click: $root.removeTaxRate, visible: actionsVisible() &amp;&amp; !isEmpty()" class="fa fa-minus-circle redlink" title="Remove item"/>
			        	</td>
			        </tr>
				</tbody>
			</table>
			&nbsp;
			
			{!! Former::checkbox('invoice_taxes')->text(trans('texts.enable_invoice_tax'))
				->label(trans('texts.settings'))->data_bind('checked: $root.invoice_taxes, enable: $root.tax_rates().length > 1') !!}
			{!! Former::checkbox('invoice_item_taxes')->text(trans('texts.enable_line_item_tax'))
				->label('&nbsp;')->data_bind('checked: $root.invoice_item_taxes, enable: $root.tax_rates().length > 1') !!}
            {!! Former::checkbox('show_item_taxes')->text(trans('texts.show_line_item_tax'))
                ->label('&nbsp;')->data_bind('checked: $root.show_item_taxes, enable: $root.tax_rates().length > 1') !!}

			<br/>

		</div>

	     <div class="modal-footer" style="margin-top: 0px">
	      	<!-- <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button> -->
	        <button type="button" class="btn btn-primary" data-bind="click: $root.taxFormComplete">{{ trans('texts.done') }}</button>	      	
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

	    <div style="background-color: #fff; padding-left: 16px; padding-right: 16px">
	    	&nbsp; {!! isset($recurringHelp) ? $recurringHelp : '' !!} &nbsp;
		</div>

	     <div class="modal-footer" style="margin-top: 0px">
	      	<button type="button" class="btn btn-primary" data-dismiss="modal">{{ trans('texts.close') }}</button>
	     </div>
	  		
	    </div>
	  </div>
	</div>

	{!! Former::close() !!}

    </div>

	<script type="text/javascript">
	
	function showLearnMore() {
		$('#recurringModal').modal('show');			
	}

	$(function() {
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

		@if ($client && !$invoice)
			$('input[name=client]').val({{ $client->public_id }});
		@endif

		/*
		if (clients.length == 0) {
			$('.client_select input.form-control').prop('disabled', true);
		}
		*/
		
		var $input = $('select#client');
		$input.combobox().on('change', function(e) {
			var clientId = parseInt($('input[name=client]').val(), 10);		
            if (clientId > 0) { 
				model.loadClient(clientMap[clientId]);
                // we enable searching by contact but the selection must be the client 
                $('.client-input').val(getClientDisplayName(clientMap[clientId]));
			} else {
				model.loadClient($.parseJSON(ko.toJSON(new ClientModel())));
				model.invoice().client().country = false;				
			}            
            refreshPDF(true);
		});

		// If no clients exists show the client form when clicking on the client select input
		if (clients.length === 0) {
			$('.client_select input.form-control').on('click', function() {
				model.showClientForm();
			});
		}		

		$('#invoice_footer, #terms, #public_notes, #invoice_number, #invoice_date, #due_date, #po_number, #discount, #currency_id, #invoice_design_id, #recurring, #is_amount_discount, #partial').change(function() {
			setTimeout(function() {                
				refreshPDF(true);
			}, 1);
		});

        $('.frequency_id .input-group-addon').click(function() {
            showLearnMore();
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

		@if ($client || $invoice || count($clients) == 0)
			$('#invoice_number').focus();
		@else
			$('.client_select input.form-control').focus();			
		@endif
		
		$('#clientModal').on('shown.bs.modal', function () {
			$('#name').focus();			
		}).on('hidden.bs.modal', function () {
			if (model.clientBackup) {
				model.loadClient(model.clientBackup);
				refreshPDF(true);
			}
		})
		
		$('#taxModal').on('shown.bs.modal', function () {
			$('#taxModal input:first').focus();			
		}).on('hidden.bs.modal', function () {
			// if the user changed the tax rates we need to trigger the
			// change event on the selects for the model to get updated
			$('table.invoice-table select').trigger('change');
		})

		$('#relatedActions > button:first').click(function() {
			onPaymentClick();
		});

		$('label.radio').addClass('radio-inline');

		applyComboboxListeners();
		
		@if ($client)
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
	});	

	function applyComboboxListeners() {
        var selectorStr = '.invoice-table input, .invoice-table select, .invoice-table textarea';		
		$(selectorStr).off('blur').on('blur', function() {
			refreshPDF(true);
		});

        $('textarea').on('keyup focus', function(e) {            
            while($(this).outerHeight() < this.scrollHeight + parseFloat($(this).css("borderTopWidth")) + parseFloat($(this).css("borderBottomWidth"))) {
                $(this).height($(this).height()+1);
            };
        });        

		@if (Auth::user()->account->fill_products)
			$('.datalist').on('input', function() {			                
				var key = $(this).val();
				for (var i=0; i<products.length; i++) {
					var product = products[i];
					if (product.product_key == key) {
						var model = ko.dataFor(this);					
                        if (!model.notes()) {
						  model.notes(product.notes);
                        }
                        if (!model.cost()) {
						  model.cost(accounting.toFixed(product.cost,2));
                        }
                        if (!model.qty()) {
						  model.qty(1);
                        }
						break;
					}
				}
                onItemChange();
                refreshPDF();
			});
		@endif
	}

	function createInvoiceModel() {
		var invoice = ko.toJS(model).invoice;
		invoice.is_pro = {{ Auth::user()->isPro() ? 'true' : 'false' }};
		invoice.is_quote = {{ $entityType == ENTITY_QUOTE ? 'true' : 'false' }};
		invoice.contact = _.findWhere(invoice.client.contacts, {send_invoice: true});
        invoice.account.show_item_taxes = $('#show_item_taxes').is(':checked');

        if (invoice.is_recurring) {
            invoice.invoice_number = '0000';
        }

        @if (!$invoice)
            if (!invoice.terms) {
                invoice.terms = wordWrapText('{!! str_replace(["\r\n","\r","\n"], '\n', addslashes($account->invoice_terms)) !!}', 300);
            }
            if (!invoice.invoice_footer) {
                invoice.invoice_footer = wordWrapText('{!! str_replace(["\r\n","\r","\n"], '\n', addslashes($account->invoice_footer)) !!}', 600);
            }
        @endif

		@if (file_exists($account->getLogoPath()))
			invoice.image = "{{ HTML::image_data($account->getLogoPath()) }}";
			invoice.imageWidth = {{ $account->getLogoWidth() }};
			invoice.imageHeight = {{ $account->getLogoHeight() }};
		@endif

        invoiceLabels.item = invoice.has_tasks ? invoiceLabels.date : invoiceLabels.item_orig;
        invoiceLabels.quantity = invoice.has_tasks ? invoiceLabels.hours : invoiceLabels.quantity_orig;
        invoiceLabels.unit_cost = invoice.has_tasks ? invoiceLabels.rate : invoiceLabels.unit_cost_orig;        

        return invoice;
	}

	function getPDFString(cb, force) {
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
        if (confirm('{!! trans("texts.are_you_sure") !!}')) {
            model.invoice().terms(model.invoice().default_terms());
            refreshPDF();
        }
        return false;
    }

    function resetFooter() {
        if (confirm('{!! trans("texts.are_you_sure") !!}')) {
            model.invoice().invoice_footer(model.invoice().default_footer());
            refreshPDF();
        }
        return false;
    }

	function onDownloadClick() {
		trackEvent('/activity', '/download_pdf');
		var invoice = createInvoiceModel();
		var design  = getDesignJavascript();
		if (!design) return;
		var doc = generatePDF(invoice, design, true);
        var type = invoice.is_quote ? '{{ trans('texts.'.ENTITY_QUOTE) }}' : '{{ trans('texts.'.ENTITY_INVOICE) }}';
		doc.save(type +'-' + $('#invoice_number').val() + '.pdf');
	}

	function onEmailClick() {
        if (!NINJA.isRegistered) {
            alert("{!! trans('texts.registration_required') !!}");
            return;
        }

		if (confirm('{!! trans("texts.confirm_email_$entityType") !!}' + '\n\n' + getSendToEmails())) {
			preparePdfData('email');
		}
	}

	function onSaveClick() {
		if (model.invoice().is_recurring()) {
			if (confirm("{!! trans("texts.confirm_recurring_email_$entityType") !!}" + '\n\n' + getSendToEmails() + '\n' + "{!! trans("texts.confirm_recurring_timing") !!}")) {
				submitAction('');
			}
		} else {
            preparePdfData('');
		}
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
        var design  = getDesignJavascript();
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
			return;
		}
        onPartialChange(true);
		$('#action').val(value);
		$('#submitButton').click();
	}

	function isSaveValid() {
		var isValid = false;
		for (var i=0; i<model.invoice().client().contacts().length; i++) {
			var contact = model.invoice().client().contacts()[i];
			if (isValidEmailAddress(contact.email()) || contact.first_name()) {
				isValid = true;
			} else {
				isValid = false;
				break;
			}
		}
		return isValid;
	}
	
	function isEmailValid() {
		var isValid = false;
		var sendTo = false;
		var client = self.invoice().client();
		for (var i=0; i<client.contacts().length; i++) {
			var contact = client.contacts()[i];        		
			if (isValidEmailAddress(contact.email())) {
				isValid = true;
				if (contact.send_invoice() || client.contacts().length == 1) {
					sendTo = true;
				}
			} else {
				isValid = false;
				break;
			}
		}
		return (isValid && sendTo)
	}

	function onMarkClick() {
		submitAction('mark');
	}

	function onCloneClick() {
		submitAction('clone');
	}

	function onConvertClick() {
		submitAction('convert');		
	}

	@if ($client && $invoice)
	function onPaymentClick() {
		window.location = '{{ URL::to('payments/create/' . $client->public_id . '/' . $invoice->public_id ) }}';
	}

	function onCreditClick() {
		window.location = '{{ URL::to('credits/create/' . $client->public_id . '/' . $invoice->public_id ) }}';
	}
	@endif

	function onArchiveClick() {
		submitAction('archive');			
	}

	function onDeleteClick() {
        if (confirm('{!! trans("texts.are_you_sure") !!}')) {		
			submitAction('delete');		
		}		
	}

	function formEnterClick(event) {
		if (event.keyCode === 13){
			if (event.target.type == 'textarea') {
				return;
			}
			event.preventDefault();		     				

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

	function taxModalEnterClick(event) {		
		if (event.keyCode === 13){
			event.preventDefault();		     	
            model.taxFormComplete();
            return false;
        }
	}

	function ViewModel(data) {
		var self = this;
        self.showMore = ko.observable(false);

		//self.invoice = data ? false : new InvoiceModel();
		self.invoice = ko.observable(data ? false : new InvoiceModel());
		self.tax_rates = ko.observableArray();

		self.loadClient = function(client) {
			ko.mapping.fromJS(client, model.invoice().client().mapping, model.invoice().client);
            @if (!$invoice)
			 self.setDueDate();
            @endif
		}

        self.showMoreFields = function() {
            self.showMore(!self.showMore());
        }

		self.setDueDate = function() {
            @if ($entityType == ENTITY_INVOICE)
            var paymentTerms = parseInt(self.invoice().client().payment_terms());
            if (paymentTerms && paymentTerms != 0 && !self.invoice().due_date())
			{
                if (paymentTerms == -1) paymentTerms = 0;
				var dueDate = $('#invoice_date').datepicker('getDate');
				dueDate.setDate(dueDate.getDate() + paymentTerms);
				self.invoice().due_date(dueDate);	
				// We're using the datepicker to handle the date formatting 
				self.invoice().due_date($('#due_date').val());
			}			
            @endif
		}

		self.invoice_taxes = ko.observable({{ Auth::user()->account->invoice_taxes ? 'true' : 'false' }});
		self.invoice_item_taxes = ko.observable({{ Auth::user()->account->invoice_item_taxes ? 'true' : 'false' }});
        self.show_item_taxes = ko.observable({{ Auth::user()->account->show_item_taxes ? 'true' : 'false' }});
		
		self.mapping = {
		    'invoice': {
		        create: function(options) {
		            return new InvoiceModel(options.data);
		        }
		    },
		    'tax_rates': {
		    	create: function(options) {
		    		return new TaxRateModel(options.data);
		    	}
		    },
		}		

		if (data) {
			ko.mapping.fromJS(data, self.mapping, self);
		}

		self.invoice_taxes.show = ko.computed(function() {
			if (self.tax_rates().length > 2 && self.invoice_taxes()) {
				return true;
			}
			if (self.invoice().tax_rate() > 0) {
				return true;
			}			
			return false;
		});

		self.invoice_item_taxes.show = ko.computed(function() {
			if (self.tax_rates().length > 2 && self.invoice_item_taxes()) {
				return true;
			}
			for (var i=0; i<self.invoice().invoice_items().length; i++) {
				var item = self.invoice().invoice_items()[i];
				if (item.tax_rate() > 0) {
					return true;
				}
			}
			return false;
		});

		self.tax_rates.filtered = ko.computed(function() {
			var i = 0;
			for (i; i<self.tax_rates().length; i++) {
				var taxRate = self.tax_rates()[i];
				if (taxRate.isEmpty()) {
					break;
				}
			}

			var rates = self.tax_rates().concat();
			rates.splice(i, 1);
			return rates;
		});
		

		self.removeTaxRate = function(taxRate) {
			self.tax_rates.remove(taxRate);
			//refreshPDF();
		}

		self.addTaxRate = function(data) {
			var itemModel = new TaxRateModel(data);
			self.tax_rates.push(itemModel);	
			applyComboboxListeners();
		}		

		/*
		self.getBlankTaxRate = function() {
			for (var i=0; i<self.tax_rates().length; i++) {
				var taxRate = self.tax_rates()[i];
				if (!taxRate.name()) {
					return taxRate;
				}
			}
		}
		*/

		self.getTaxRate = function(name, rate) {
			for (var i=0; i<self.tax_rates().length; i++) {
				var taxRate = self.tax_rates()[i];
				if (taxRate.name() == name && taxRate.rate() == parseFloat(rate)) {
					return taxRate;
				}			
			}			

			var taxRate = new TaxRateModel();
			taxRate.name(name);
			taxRate.rate(parseFloat(rate));
			if (name) {
               taxRate.is_deleted(true);
			   self.tax_rates.push(taxRate);
            }
			return taxRate;			
		}		

		self.showTaxesForm = function() {
			self.taxBackup = ko.mapping.toJS(self.tax_rates);

			$('#taxModal').modal('show');	
		}	

		self.taxFormComplete = function() {
			model.taxBackup = false;
			$('#taxModal').modal('hide');
            refreshPDF();
		}

		self.showClientForm = function() {
			trackEvent('/activity', '/view_client_form');
			self.clientBackup = ko.mapping.toJS(self.invoice().client);

			$('#emailError').css( "display", "none" );			
			$('#clientModal').modal('show');			
		}

		self.clientFormComplete = function() {
			trackEvent('/activity', '/save_client_form');

            var email = $('#email0').val();
            var firstName = $('#first_name').val();
            var lastName = $('#last_name').val();
            var name = $('#name').val();

            if (name) {
                //
            } else if (firstName || lastName) {
                name = firstName + ' ' + lastName;
            } else {
                name = email;
            }

			var isValid = true;
			$("input[name='email']").each(function(item, value) {
				var email = $(value).val();
				if (!name && (!email || !isValidEmailAddress(email))) {
					isValid = false;					
				}
			});
			if (!isValid) {
				$('#emailError').css( "display", "inline" );
				return;
			}

			if (self.invoice().client().public_id() == 0) {
				self.invoice().client().public_id(-1);
			}

			model.setDueDate();

			setComboboxValue($('.client_select'), -1, name);

			//$('.client_select select').combobox('setSelected');
			//$('.client_select input.form-control').val(name);
			//$('.client_select .combobox-container').addClass('combobox-selected');

			$('#emailError').css( "display", "none" );
			//$('.client_select input.form-control').focus();						

			refreshPDF(true);
			model.clientBackup = false;
			$('#clientModal').modal('hide');						
		}		

		self.clientLinkText = ko.computed(function() {
			if (self.invoice().client().public_id())
			{
				return "{{ trans('texts.edit_client') }}";
			}
			else
			{
				if (clients.length > {{ Auth::user()->getMaxNumClients() }})
				{
					return '';
				}
				else
				{
					return "{{ trans('texts.create_new_client') }}";
				}
			}
    });
	}

	function InvoiceModel(data) {
		var self = this;		
		this.client = ko.observable(data ? false : new ClientModel());		
		self.account = {!! $account !!};
		self.id = ko.observable('');
		self.discount = ko.observable('');
		self.is_amount_discount = ko.observable(0);
		self.frequency_id = ko.observable(4); // default to monthly 
        self.terms = ko.observable('');
        self.default_terms = ko.observable("{{ str_replace(["\r\n","\r","\n"], '\n', addslashes($account->invoice_terms)) }}");
        self.terms_placeholder = ko.observable({{ !$invoice && $account->invoice_terms ? 'true' : 'false' }} ? self.default_terms() : '');
        self.set_default_terms = ko.observable(false);
        self.invoice_footer = ko.observable('');
        self.default_footer = ko.observable("{{ str_replace(["\r\n","\r","\n"], '\n', addslashes($account->invoice_footer)) }}");
        self.footer_placeholder = ko.observable({{ !$invoice && $account->invoice_footer ? 'true' : 'false' }} ? self.default_footer() : '');
        self.set_default_footer = ko.observable(false);
		self.public_notes = ko.observable('');		
		self.po_number = ko.observable('');
		self.invoice_date = ko.observable('{{ Utils::today() }}');
		self.invoice_number = ko.observable('{{ isset($invoiceNumber) ? $invoiceNumber : '' }}');
		self.due_date = ko.observable('');
		self.start_date = ko.observable('{{ Utils::today() }}');
		self.end_date = ko.observable('');
        self.last_sent_date = ko.observable('');
		self.tax_name = ko.observable();
		self.tax_rate = ko.observable();
		self.is_recurring = ko.observable({{ $isRecurring ? 'true' : 'false' }});
        self.auto_bill = ko.observable();
		self.invoice_status_id = ko.observable(0);
		self.invoice_items = ko.observableArray();
		self.amount = ko.observable(0);
		self.balance = ko.observable(0);
		self.invoice_design_id = ko.observable({{ $account->invoice_design_id }});
        self.partial = ko.observable(0);            
        self.has_tasks = ko.observable(false);

		self.custom_value1 = ko.observable(0);
		self.custom_value2 = ko.observable(0);
		self.custom_taxes1 = ko.observable(false);
		self.custom_taxes2 = ko.observable(false);

		self.mapping = {
			'client': {
		        create: function(options) {
                    return new ClientModel(options.data);
		        }
			},
		    'invoice_items': {
		        create: function(options) {
		            return new ItemModel(options.data);
		        }
		    },
		    'tax': {
		    	create: function(options) {
		    		return new TaxRateModel(options.data);
		    	}
		    },
		}

		self.addItem = function() {
			var itemModel = new ItemModel();
			@if ($account->hide_quantity)
				itemModel.qty(1);
			@endif
			self.invoice_items.push(itemModel);	
			applyComboboxListeners();			
            return itemModel;
		}

        if (data) {
			ko.mapping.fromJS(data, self.mapping, self);			
		} else {
			self.addItem();
		}

        self.productLabel = ko.computed(function() {
            return self.has_tasks() ? invoiceLabels['date'] : invoiceLabels['item'];
        }, this);
        
        self.qtyLabel = ko.computed(function() {
            return self.has_tasks() ? invoiceLabels['hours'] : invoiceLabels['quantity'];
        }, this);
        
        self.costLabel = ko.computed(function() {
            return self.has_tasks() ? invoiceLabels['rate'] : invoiceLabels['unit_cost'];
        }, this);
        
		self._tax = ko.observable();
		this.tax = ko.computed({
			read: function () {
				return self._tax();
			},
			write: function(value) {
				if (value) {
					self._tax(value);								
					self.tax_name(value.name());
					self.tax_rate(value.rate());
				} else {
					self._tax(false);								
					self.tax_name('');
					self.tax_rate(0);
				}
			}
		})

		self.wrapped_terms = ko.computed({
			read: function() {
				return this.terms();
			},
			write: function(value) {
				value = wordWrapText(value, 300);
				self.terms(value);
			},
			owner: this
		});


        self.wrapped_notes = ko.computed({
            read: function() {                
                return this.public_notes();
            },
            write: function(value) {
                value = wordWrapText(value, 300);
                self.public_notes(value);
            },
            owner: this
        });

        self.wrapped_footer = ko.computed({
            read: function() {
                return this.invoice_footer();
            },
            write: function(value) {
                value = wordWrapText(value, 600);
                self.invoice_footer(value);
            },
            owner: this
        });

		self.removeItem = function(item) {
			self.invoice_items.remove(item);
			refreshPDF(true);
		}


		self.totals = ko.observable();

		self.totals.rawSubtotal = ko.computed(function() {
		    var total = 0;
		    for(var p=0; p < self.invoice_items().length; ++p) {
		       var item = self.invoice_items()[p];
	           total += item.totals.rawTotal();
		    }
		    return total;
		});

		self.totals.subtotal = ko.computed(function() {
		    var total = self.totals.rawSubtotal();
		    return total > 0 ? formatMoney(total, self.client().currency_id()) : '';
		});

		self.totals.rawDiscounted = ko.computed(function() {
			if (parseInt(self.is_amount_discount())) {
				return roundToTwo(self.discount());
			} else {
				return roundToTwo(self.totals.rawSubtotal() * (self.discount()/100));			
			}
		});

		self.totals.discounted = ko.computed(function() {
			return formatMoney(self.totals.rawDiscounted(), self.client().currency_id());
		});

		self.totals.taxAmount = ko.computed(function() {
    	    var total = self.totals.rawSubtotal();
    	    var discount = self.totals.rawDiscounted();
    	    total -= discount;

    	    var customValue1 = roundToTwo(self.custom_value1());
    	    var customValue2 = roundToTwo(self.custom_value2());
    	    var customTaxes1 = self.custom_taxes1() == 1;
    	    var customTaxes2 = self.custom_taxes2() == 1;
    	    
    	    if (customValue1 && customTaxes1) {
    	    	total = NINJA.parseFloat(total) + customValue1;
    	    }
    	    if (customValue2 && customTaxes2) {
    	    	total = NINJA.parseFloat(total) + customValue2;
    	    }

			var taxRate = parseFloat(self.tax_rate());
			if (taxRate > 0) {
				var tax = roundToTwo(total * (taxRate/100));			
        		return formatMoney(tax, self.client().currency_id());
        	} else {
        		return formatMoney(0, self.client().currency_id());
        	}
    	});

        self.totals.itemTaxes = ko.computed(function() {
            var taxes = {};
            var total = self.totals.rawSubtotal();
            for(var i=0; i<self.invoice_items().length; i++) {
                var item = self.invoice_items()[i];
                var lineTotal = item.totals.rawTotal();
                if (self.discount()) {
                    if (parseInt(self.is_amount_discount())) {
                        lineTotal -= roundToTwo((lineTotal/total) * self.discount());
                    } else {
                        lineTotal -= roundToTwo(lineTotal * (self.discount()/100));
                    }
                }
                var taxAmount = roundToTwo(lineTotal * item.tax_rate() / 100);
                if (taxAmount) {
                    var key = item.tax_name() + item.tax_rate();
                    if (taxes.hasOwnProperty(key)) {
                        taxes[key].amount += taxAmount;
                    } else {
                        taxes[key] = {name:item.tax_name(), rate:item.tax_rate(), amount:taxAmount};
                    }
                }               
            }
            return taxes;
        });

        self.totals.hasItemTaxes = ko.computed(function() {
            var count = 0;
            var taxes = self.totals.itemTaxes();
            for (var key in taxes) {
                if (taxes.hasOwnProperty(key)) {
                    count++;
                }
            }
            return count > 0;
        });

        self.totals.itemTaxRates = ko.computed(function() {            
            var taxes = self.totals.itemTaxes();
            var parts = [];            
            for (var key in taxes) {
                if (taxes.hasOwnProperty(key)) {
                    parts.push(taxes[key].name + ' ' + (taxes[key].rate*1) + '%');
                }                
            }
            return parts.join('<br/>');
        });

        self.totals.itemTaxAmounts = ko.computed(function() {
            var taxes = self.totals.itemTaxes();
            var parts = [];            
            for (var key in taxes) {
                if (taxes.hasOwnProperty(key)) {
                    parts.push(formatMoney(taxes[key].amount, self.client().currency_id()));
                }                
            }
            return parts.join('<br/>');
        });

		self.totals.rawPaidToDate = ko.computed(function() {
			return accounting.toFixed(self.amount(),2) - accounting.toFixed(self.balance(),2);
		});

		self.totals.paidToDate = ko.computed(function() {
			var total = self.totals.rawPaidToDate();
		    return formatMoney(total, self.client().currency_id());
		});

		self.totals.rawTotal = ko.computed(function() {
    	    var total = accounting.toFixed(self.totals.rawSubtotal(),2);	    
    	    var discount = self.totals.rawDiscounted();
    	    total -= discount;

    	    var customValue1 = roundToTwo(self.custom_value1());
    	    var customValue2 = roundToTwo(self.custom_value2());
    	    var customTaxes1 = self.custom_taxes1() == 1;
    	    var customTaxes2 = self.custom_taxes2() == 1;
    	    
    	    if (customValue1 && customTaxes1) {
    	    	total = NINJA.parseFloat(total) + customValue1;
    	    }
    	    if (customValue2 && customTaxes2) {
    	    	total = NINJA.parseFloat(total) + customValue2;
    	    }

			var taxRate = parseFloat(self.tax_rate());
			if (taxRate > 0) {
        		total = NINJA.parseFloat(total) + roundToTwo((total * (taxRate/100)));
        	}

            var taxes = self.totals.itemTaxes();
            for (var key in taxes) {
                if (taxes.hasOwnProperty(key)) {
                    total += taxes[key].amount;
                }
            }

    	    if (customValue1 && !customTaxes1) {
    	    	total = NINJA.parseFloat(total) + customValue1;
    	    }
    	    if (customValue2 && !customTaxes2) {
    	    	total = NINJA.parseFloat(total) + customValue2;
    	    }
    	    
        	var paid = self.totals.rawPaidToDate();
        	if (paid > 0) {
        		total -= paid;
        	}

    	    return total;
      	});

        self.totals.total = ko.computed(function() {
            return formatMoney(self.partial() ? self.partial() : self.totals.rawTotal(), self.client().currency_id());
        });        

      	self.onDragged = function(item) {
      		refreshPDF(true);
      	}
	}

	function ClientModel(data) {
		var self = this;
		self.public_id = ko.observable(0);
		self.name = ko.observable('');
        self.id_number = ko.observable('');
        self.vat_number = ko.observable('');
		self.work_phone = ko.observable('');
		self.custom_value1 = ko.observable('');
		self.custom_value2 = ko.observable('');
		self.private_notes = ko.observable('');
		self.address1 = ko.observable('');
		self.address2 = ko.observable('');
		self.city = ko.observable('');
		self.state = ko.observable('');
		self.postal_code = ko.observable('');
		self.country_id = ko.observable('');
		self.size_id = ko.observable('');
		self.industry_id = ko.observable('');
		self.currency_id = ko.observable('');
        self.language_id = ko.observable('');
		self.website = ko.observable('');
		self.payment_terms = ko.observable(0);
		self.contacts = ko.observableArray();

		self.mapping = {
	    	'contacts': {
	        	create: function(options) {
	        		var model = new ContactModel(options.data);
	        		model.send_invoice(options.data.send_invoice == '1');
	        		return model;
	        	}
	    	}
		}

		self.showContact = function(elem) { if (elem.nodeType === 1) $(elem).hide().slideDown() }
		self.hideContact = function(elem) { if (elem.nodeType === 1) $(elem).slideUp(function() { $(elem).remove(); }) }

		self.addContact = function() {
            var contact = new ContactModel();
			contact.send_invoice(true);
			self.contacts.push(contact);
			return false;
		}

		self.removeContact = function() {
			self.contacts.remove(this);			
		}

		self.name.display = ko.computed(function() {
			if (self.name()) {
				return self.name();
			}
			if (self.contacts().length == 0) return;
			var contact = self.contacts()[0];			
			if (contact.first_name() || contact.last_name()) {
				return contact.first_name() + ' ' + contact.last_name();				
			} else {
				return contact.email();
			}
		});				
	
		self.name.placeholder = ko.computed(function() {
			if (self.contacts().length == 0) return '';
			var contact = self.contacts()[0];
			if (contact.first_name() || contact.last_name()) {
				return contact.first_name() + ' ' + contact.last_name();
			} else {
				return contact.email();
			}
		});	

		if (data) {
			ko.mapping.fromJS(data, {}, this);
		} else {
			self.addContact();
		}		
	}

	function ContactModel(data) {
		var self = this;
		self.public_id = ko.observable('');
		self.first_name = ko.observable('');
		self.last_name = ko.observable('');
		self.email = ko.observable('');
		self.phone = ko.observable('');		
		self.send_invoice = ko.observable(false);
		self.invitation_link = ko.observable('');		

        if (data) {
            ko.mapping.fromJS(data, {}, this);      
        }       

        self.displayName = ko.computed(function() {
            var str = '';
            if (self.first_name() || self.last_name()) {
                str += self.first_name() + ' ' + self.last_name() + '\n';
            }           
            if (self.email()) {
                str += self.email() + '\n';
            }           

            return str;
        });

		self.email.display = ko.computed(function() {
			var str = '';
			if (self.first_name() || self.last_name()) {
				str += self.first_name() + ' ' + self.last_name() + '<br/>';
			}			
            if (self.email()) {
                str += self.email() + '<br/>';    
            }			

			@if (Utils::isConfirmed())
			if (self.invitation_link()) {
				str += '<a href="' + self.invitation_link() + '" target="_blank">{{ trans('texts.view_as_recipient') }}</a>';
			}
			@endif
			
			return str;
		});		
	}

	function TaxRateModel(data) {
		var self = this;
		self.public_id = ko.observable('');
		self.rate = ko.observable(0);
		self.name = ko.observable('');
		self.is_deleted = ko.observable(false);
		self.is_blank = ko.observable(false);
		self.actionsVisible = ko.observable(false);

		if (data) {
			ko.mapping.fromJS(data, {}, this);		
		}		

		this.prettyRate = ko.computed({
	        read: function () {
	            return this.rate() ? roundToTwo(this.rate()) : '';
	        },
	        write: function (value) {
	            this.rate(value);
	        },
	        owner: this
	    });				


		self.displayName = ko.computed({
			read: function () {
				var name = self.name() ? self.name() : '';
				var rate = self.rate() ? parseFloat(self.rate()) + '%' : '';
				return name + ' ' + rate;
			},
	        write: function (value) {
	            // do nothing
	        },
	        owner: this			
		});	

    	self.hideActions = function() {
			self.actionsVisible(false);
    	}

    	self.showActions = function() {
			self.actionsVisible(true);
    	}		

    	self.isEmpty = function() {
    		return !self.rate() && !self.name();
    	}    	
	}

	function ItemModel(data) {
		var self = this;		
		self.product_key = ko.observable('');
		self.notes = ko.observable('');
		self.cost = ko.observable(0);
		self.qty = ko.observable(0);
		self.tax_name = ko.observable('');
		self.tax_rate = ko.observable(0);
		self.task_public_id = ko.observable('');
        self.actionsVisible = ko.observable(false);
		
		self._tax = ko.observable();
		this.tax = ko.computed({
			read: function () {
				return self._tax();
			},
			write: function(value) {
				self._tax(value);								
				self.tax_name(value.name());
				self.tax_rate(value.rate());
			}
		})

		this.prettyQty = ko.computed({
	        read: function () {
	            return NINJA.parseFloat(this.qty()) ? NINJA.parseFloat(this.qty()) : '';
	        },
	        write: function (value) {
	            this.qty(value);
	        },
	        owner: this
	    });				

		this.prettyCost = ko.computed({
	        read: function () {
	            return this.cost() ? this.cost() : '';
	        },
	        write: function (value) {
	            this.cost(value);
	        },
	        owner: this
	    });				

		self.mapping = {
		    'tax': {
		    	create: function(options) {
		    		return new TaxRateModel(options.data);
		    	}
		    }
		}

		if (data) {
			ko.mapping.fromJS(data, self.mapping, this);			
		}

		self.wrapped_notes = ko.computed({
			read: function() {
				return this.notes();
			},
			write: function(value) {
				value = wordWrapText(value, 235);
				self.notes(value);
				onItemChange();
			},
			owner: this
		});

		this.totals = ko.observable();

		this.totals.rawTotal = ko.computed(function() {
			var cost = roundToTwo(NINJA.parseFloat(self.cost()));
			var qty = roundToTwo(NINJA.parseFloat(self.qty()));
			var value = cost * qty;        	
        	return value ? roundToTwo(value) : 0;
      	});

		this.totals.total = ko.computed(function() {
			var total = self.totals.rawTotal();
			if (window.hasOwnProperty('model') && model.invoice && model.invoice() && model.invoice().client()) {
				return total ? formatMoney(total, model.invoice().client().currency_id()) : '';
			} else {
				return total ? formatMoney(total, 1) : '';
			}
  	});

  	this.hideActions = function() {
		this.actionsVisible(false);
  	}

  	this.showActions = function() {
		this.actionsVisible(true);
  	}

  	this.isEmpty = function() {
  		return !self.product_key() && !self.notes() && !self.cost() && (!self.qty() || {{ $account->hide_quantity ? 'true' : 'false' }});
  	}

  	this.onSelect = function(){              
    }
	}

	function onItemChange()
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

        /*
		$('.word-wrap').each(function(index, input) {
			$(input).height($(input).val().split('\n').length * 20);
		});
        */
	}

	function onTaxRateChange()
	{
		var emptyCount = 0;

		for(var i=0; i<model.tax_rates().length; i++) {
			var taxRate = model.tax_rates()[i];
			if (taxRate.isEmpty()) {
				emptyCount++;
			}
		}

		for(var i=0; i<2-emptyCount; i++) {
			model.addTaxRate();
		}
	}

    function onPartialChange(silent)
    {
        var val = NINJA.parseFloat($('#partial').val());
        var oldVal = val;
        val = Math.max(Math.min(val, model.invoice().totals.rawTotal()), 0);
        model.invoice().partial(val || '');
        
        if (!silent && val != oldVal) {
            $('#partial').tooltip('show');
            setTimeout(function() {
                $('#partial').tooltip('hide');
            }, 5000);
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

	var products = {!! $products !!};
	var clients = {!! $clients !!};	
	var countries = {!! $countries !!};

	var clientMap = {};
	var $clientSelect = $('select#client');
	var invoiceDesigns = {!! $invoiceDesigns !!};

	for (var i=0; i<clients.length; i++) {
		var client = clients[i];
		var clientName = getClientDisplayName(client);
        for (var j=0; j<client.contacts.length; j++) {
			var contact = client.contacts[j];
            var contactName = getContactDisplayName(contact);
			if (contact.is_primary) {
				contact.send_invoice = true;
			}
            if (clientName != contactName) {
                $clientSelect.append(new Option(contactName, client.public_id)); 
            }
		}
		clientMap[client.public_id] = client;
        $clientSelect.append(new Option(clientName, client.public_id)); 
	}

	@if ($data)
		window.model = new ViewModel({!! $data !!});        
	@else 
		window.model = new ViewModel();
		model.addTaxRate();
		@foreach ($taxRates as $taxRate)
			model.addTaxRate({!! $taxRate !!});
		@endforeach
		@if ($invoice)
			var invoice = {!! $invoice !!};
			ko.mapping.fromJS(invoice, model.invoice().mapping, model.invoice);			
            var invitationContactIds = {!! json_encode($invitationContactIds) !!};		
			var client = clientMap[invoice.client.public_id];
			if (client) { // in case it's deleted
				for (var i=0; i<client.contacts.length; i++) {
					var contact = client.contacts[i];
					contact.send_invoice = invitationContactIds.indexOf(contact.public_id) >= 0;
				}			
			}
			model.invoice().addItem();
			//model.addTaxRate();			
		@else
            // TODO: Add the first tax rate for new invoices by adding a new db field to the tax codes types to set the default
            //if(model.invoice_taxes() && model.tax_rates().length > 2) {
            //    var tax = model.tax_rates()[1];
            //    model.invoice().tax(tax);
            //}
			model.invoice().custom_taxes1({{ $account->custom_invoice_taxes1 ? 'true' : 'false' }});
			model.invoice().custom_taxes2({{ $account->custom_invoice_taxes2 ? 'true' : 'false' }});
		@endif

        @if (isset($tasks) && $tasks)
            // move the blank invoice line item to the end
            var blank = model.invoice().invoice_items.pop();
            var tasks = {!! $tasks !!};
            
            for (var i=0; i<tasks.length; i++) {
                var task = tasks[i];                    
                var item = model.invoice().addItem();
                item.notes(task.description);
                item.product_key(task.startTime);
                item.qty(task.duration);
                item.task_public_id(task.publicId);
            }        
            model.invoice().invoice_items.push(blank);
            model.invoice().has_tasks(true);
        @endif        
	@endif

	model.invoice().tax(model.getTaxRate(model.invoice().tax_name(), model.invoice().tax_rate()));			
	for (var i=0; i<model.invoice().invoice_items().length; i++) {
		var item = model.invoice().invoice_items()[i];
		item.tax(model.getTaxRate(item.tax_name(), item.tax_rate()));
		item.cost(NINJA.parseFloat(item.cost()) != 0 ? roundToTwo(item.cost(), true) : '');
	}
	onTaxRateChange();

	// display blank instead of '0'
	if (!NINJA.parseFloat(model.invoice().discount())) model.invoice().discount('');
    if (!NINJA.parseFloat(model.invoice().partial())) model.invoice().partial('');
	if (!model.invoice().custom_value1()) model.invoice().custom_value1('');
	if (!model.invoice().custom_value2()) model.invoice().custom_value2('');

	ko.applyBindings(model);	
	onItemChange();

	</script>

@stop