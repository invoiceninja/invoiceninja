@extends('header')

@section('head')
	@parent

		<script src="{{ asset('js/pdf_viewer.js') }}" type="text/javascript"></script>
		<script src="{{ asset('js/compatibility.js') }}" type="text/javascript"></script>
@stop

@section('content')
	
	@if ($invoice)
		<ol class="breadcrumb">
			<li>{{ link_to('invoices', 'Invoices') }}</li>
			<li class='active'>{{ $invoice->invoice_number }}</li>
		</ol>  
	@endif

	{{ Former::open($url)->method($method)->addClass('warn-on-exit')->rules(array(
		'client' => 'required',
		'email' => 'required',
		'product_key' => 'max:20',
	)) }}	

	<input type="submit" style="display:none" name="submitButton" id="submitButton">

	<div data-bind="with: invoice">
    <div class="row" style="min-height:195px" onkeypress="formEnterClick(event)">
    	<div class="col-md-4" id="col_1">

    		@if ($invoice)
				<div class="form-group">
					<label for="client" class="control-label col-lg-4 col-sm-4">Client</label>
					<div class="col-lg-8 col-sm-8" style="padding-top: 7px">
						<a id="editClientLink" class="pointer" data-bind="click: $root.showClientForm, text: getClientDisplayName(ko.toJS(client()))"></a>
					</div>
				</div>    				
				<div style="display:none">
    		@endif

			{{ Former::select('client')->addOption('', '')->data_bind("dropdown: client")->addGroupClass('client_select closer-row') }}

			<div class="form-group" style="margin-bottom: 8px">
				<div class="col-lg-8 col-sm-8 col-lg-offset-4 col-sm-offset-4">
					<a id="createClientLink" class="pointer" data-bind="click: $root.showClientForm, text: $root.clientLinkText"></a>					
				</div>
			</div>

			@if ($invoice)
				</div>
			@endif

			<div data-bind="with: client">
				<div style="display:none" class="form-group" data-bind="visible: contacts().length > 0 &amp;&amp; contacts()[0].email(), foreach: contacts">
					<div class="col-lg-8 col-lg-offset-4">
						<label for="test" class="checkbox" data-bind="attr: {for: $index() + '_check'}">
							<input type="checkbox" value="1" data-bind="checked: send_invoice, attr: {id: $index() + '_check'}">
								<span data-bind="html: email.display"/>
						</label>
					</div>				
				</div>
			</div>
			
		</div>
		<div class="col-md-4" id="col_2">
			<div data-bind="visible: !is_recurring()">
				{{ Former::text('invoice_date')->data_bind("datePicker: invoice_date, valueUpdate: 'afterkeydown'")
							->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT))->append('<i class="glyphicon glyphicon-calendar" onclick="toggleDatePicker(\'invoice_date\')"></i>') }}
				{{ Former::text('due_date')->data_bind("datePicker: due_date, valueUpdate: 'afterkeydown'")
							->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT))->append('<i class="glyphicon glyphicon-calendar" onclick="toggleDatePicker(\'due_date\')"></i>') }}							
			</div>
			<div data-bind="visible: is_recurring" style="display: none">
				{{ Former::select('frequency_id')->options($frequencies)->data_bind("value: frequency_id") }}
				{{ Former::text('start_date')->data_bind("datePicker: start_date, valueUpdate: 'afterkeydown'")
							->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT))->append('<i class="glyphicon glyphicon-calendar" onclick="toggleDatePicker(\'start_date\')"></i>') }}
				{{ Former::text('end_date')->data_bind("datePicker: end_date, valueUpdate: 'afterkeydown'")
							->data_date_format(Session::get(SESSION_DATE_PICKER_FORMAT))->append('<i class="glyphicon glyphicon-calendar" onclick="toggleDatePicker(\'end_date\')"></i>') }}
			</div>
			@if ($invoice && $invoice->recurring_invoice_id)
				<div class="pull-right" style="padding-top: 6px">
					Created by a {{ link_to('/invoices/'.$invoice->recurring_invoice_id, 'recurring invoice') }}
				</div>
			@else 
			<div data-bind="visible: invoice_status_id() < CONSTS.INVOICE_STATUS_SENT">
				{{ Former::checkbox('recurring')->text(trans('texts.enable').' &nbsp;&nbsp; <a href="#" onclick="showLearnMore()"><i class="glyphicon glyphicon-question-sign"></i> '.trans('texts.learn_more').'</a>')->data_bind("checked: is_recurring")
					->inlineHelp($invoice && $invoice->last_sent_date ? 'Last invoice sent ' . Utils::dateToString($invoice->last_sent_date) : '') }}
			</div>			
			@endif
			
		</div>

		<div class="col-md-4" id="col_2">
			{{ Former::text('invoice_number')->label(trans('texts.invoice_number_short'))->data_bind("value: invoice_number, valueUpdate: 'afterkeydown'") }}
			{{ Former::text('po_number')->label(trans('texts.po_number_short'))->data_bind("value: po_number, valueUpdate: 'afterkeydown'") }}				
			{{ Former::text('discount')->data_bind("value: discount, valueUpdate: 'afterkeydown'")->append('%') }}			
			{{-- Former::select('currency_id')->addOption('', '')->fromQuery($currencies, 'name', 'id')->data_bind("value: currency_id") --}}
			
			<div class="form-group" style="margin-bottom: 8px">
				<label for="recurring" class="control-label col-lg-4 col-sm-4">{{ trans('texts.taxes') }}</label>
				<div class="col-lg-8 col-sm-8" style="padding-top: 7px">
					<a href="#" data-bind="click: $root.showTaxesForm"><i class="glyphicon glyphicon-list-alt"></i> {{ trans('texts.manage_rates') }}</a>
				</div>
			</div>

		</div>
	</div>

	<p>&nbsp;</p>

	{{ Former::hidden('data')->data_bind("value: ko.mapping.toJSON(model)") }}	

	<table class="table invoice-table" style="margin-bottom: 0px !important">
	    <thead>
	        <tr>
	        	<th style="min-width:32px;" class="hide-border"></th>
	        	<th style="min-width:160px">{{ trans('texts.item') }}</th>
	        	<th style="width:100%">{{ trans('texts.description') }}</th>
	        	<th style="min-width:120px">{{ trans('texts.unit_cost') }}</th>
	        	<th style="min-width:120px">{{ trans('texts.quantity') }}</th>
	        	<th style="min-width:120px;display:none;" data-bind="visible: $root.invoice_item_taxes.show">{{ trans('texts.tax') }}</th>
	        	<th style="min-width:120px;">{{ trans('texts.line_total') }}</th>
	        	<th style="min-width:32px;" class="hide-border"></th>
	        </tr>
	    </thead>
	    <tbody data-bind="sortable: { data: invoice_items, afterMove: onDragged }">
	    	<tr data-bind="event: { mouseover: showActions, mouseout: hideActions }" class="sortable-row">
	        	<td class="hide-border td-icon">
	        		<i style="display:none" data-bind="visible: actionsVisible() &amp;&amp; $parent.invoice_items().length > 1" class="fa fa-sort"></i>
	        	</td>
	            <td>	            	
	            	{{ Former::text('product_key')->useDatalist($products, 'product_key')->onkeyup('onItemChange()')
	            		->raw()->data_bind("value: product_key, valueUpdate: 'afterkeydown'")->addClass('datalist') }}
	            </td>
	            <td>
	            	<textarea data-bind="value: wrapped_notes, valueUpdate: 'afterkeydown'" rows="1" cols="60" style="resize: none;" class="form-control word-wrap"></textarea>
	            </td>
	            <td>
	            	<input onkeyup="onItemChange()" data-bind="value: prettyCost, valueUpdate: 'afterkeydown'" style="text-align: right" class="form-control"//>
	            </td>
	            <td>
	            	<input onkeyup="onItemChange()" data-bind="value: prettyQty, valueUpdate: 'afterkeydown'" style="text-align: right" class="form-control"//>
	            </td>
	            <td style="display:none;" data-bind="visible: $root.invoice_item_taxes.show">
	            	<select class="form-control" style="width:100%" data-bind="value: tax, options: $root.tax_rates, optionsText: 'displayName'"></select>
	            </td>
		        	<td style="text-align:right;padding-top:9px !important">
	            	<div class="line-total" data-bind="text: totals.total"></div>
	            </td>
	        	<td style="cursor:pointer" class="hide-border td-icon">
	        		&nbsp;<i style="display:none" data-bind="click: $parent.removeItem, visible: actionsVisible() &amp;&amp; $parent.invoice_items().length > 1" class="fa fa-minus-circle redlink" title="Remove item"/>
	        	</td>
	        </tr>
		</tbody>
		<tfoot>
			<tr>
	        	<td class="hide-border"/>
	        	<td colspan="2" rowspan="5">
	        		<br/>
					{{ Former::textarea('public_notes')->data_bind("value: wrapped_notes, valueUpdate: 'afterkeydown'")
						->label(false)->placeholder(trans('texts.note_to_client'))->style('width: 520px; resize: none') }}			
					{{ Former::textarea('terms')->data_bind("value: wrapped_terms, valueUpdate: 'afterkeydown'")
						->label(false)->placeholder(trans('texts.invoice_terms'))->style('width: 520px; resize: none')
						->addGroupClass('less-space-bottom') }}
					<label class="checkbox" style="width: 200px">
						<input type="checkbox" style="width: 24px" data-bind="checked: set_default_terms"/>{{ trans('texts.save_as_default_terms') }}
					</label>
	        	</td>
	        	<td style="display:none" data-bind="visible: $root.invoice_item_taxes.show"/>	        	
				<td colspan="2">{{ trans('texts.subtotal') }}</td>
				<td style="text-align: right"><span data-bind="text: totals.subtotal"/></td>
	        </tr>
	        <tr style="display:none" data-bind="visible: discount() > 0">
	        	<td class="hide-border" colspan="3"/>
	        	<td style="display:none" class="hide-border" data-bind="visible: $root.invoice_item_taxes.show"/>
				<td colspan="2">{{ trans('texts.discount') }}</td>
				<td style="text-align: right"><span data-bind="text: totals.discounted"/></td>
	        </tr>
	        <tr style="display:none" data-bind="visible: $root.invoice_taxes.show">
	        	<td class="hide-border" colspan="3"/>
	        	<td style="display:none" class="hide-border" data-bind="visible: $root.invoice_item_taxes.show"/>	        	
				<td>{{ trans('texts.tax') }}</td>
				<td style="min-width:120px"><select class="form-control" style="width:100%" data-bind="value: tax, options: $root.tax_rates, optionsText: 'displayName'"></select></td>
				<td style="text-align: right"><span data-bind="text: totals.taxAmount"/></td>
	        </tr>
	        <tr>
	        	<td class="hide-border" colspan="3"/>
	        	<td style="display:none" class="hide-border" data-bind="visible: $root.invoice_item_taxes.show"/>	        	
				<td colspan="2">{{ trans('texts.paid_to_date') }}</td>
				<td style="text-align: right" data-bind="text: totals.paidToDate"></td>
	        </tr>	        
	        <tr>
	        	<td class="hide-border" colspan="3"/>
	        	<td style="display:none" class="hide-border" data-bind="visible: $root.invoice_item_taxes.show"/>	        	
				<td colspan="2"><b>{{ trans('texts.balance_due') }}</b></td>
				<td style="text-align: right"><span data-bind="text: totals.total"/></td>
	        </tr>
	    </tfoot>
	</table>

	<p>&nbsp;</p>
	<div class="form-actions">

		<div style="display:none">
			{{ Former::text('action') }}
			@if ($invoice)
				{{ Former::populateField('id', $invoice->public_id) }}
				{{ Former::text('id') }}		
			@endif
		</div>



		{{ Former::select('invoice_design_id')->style('display:inline;width:120px')->raw()
					->fromQuery($invoiceDesigns, 'name', 'id')->data_bind("value: invoice_design_id") }}

				
		{{ Button::primary(trans('texts.download_pdf'), array('onclick' => 'onDownloadClick()'))->append_with_icon('download-alt'); }}	
        
		@if (!$invoice || (!$invoice->trashed() && !$invoice->client->trashed()))						
			@if ($invoice)		

				<div id="primaryActions" style="text-align:left" class="btn-group">
					<button class="btn-success btn" type="button">{{ trans('texts.save_invoice') }}</button>
					<button class="btn-success btn dropdown-toggle" type="button" data-toggle="dropdown"> 
						<span class="caret"></span>
					</button>
					<ul class="dropdown-menu">
						<li><a href="javascript:onSaveClick()" id="saveButton">{{ trans('texts.save_invoice') }}</a></li>
						<li><a href="javascript:onCloneClick()">{{ trans('texts.clone_invoice') }}</a></li>
						<li class="divider"></li>
						<li><a href="javascript:onArchiveClick()">{{ trans('texts.archive_invoice') }}</a></li>
						<li><a href="javascript:onDeleteClick()">{{ trans('texts.delete_invoice') }}</a></li>
					</ul>
				</div>		

				{{-- DropdownButton::normal('Download PDF',
					  Navigation::links(
					    array(
					    	array('Download PDF', "javascript:onDownloadClick()"),
					     	array(Navigation::DIVIDER),
					     	array('Create Payment', "javascript:onPaymentClick()"),
					     	array('Create Credit', "javascript:onCreditClick()"),
					    )
					  )
					, array('id'=>'relatedActions', 'style'=>'text-align:left'))->split(); --}}				

				{{-- DropdownButton::primary('Save Invoice',
					  Navigation::links(
					    array(
					    	array('Save Invoice', "javascript:onSaveClick()"),
					     	array('Clone Invoice', "javascript:onCloneClick()"),
					     	array(Navigation::DIVIDER),
					     	array('Archive Invoice', "javascript:onArchiveClick()"),
					     	array('Delete Invoice', "javascript:onDeleteClick()"),
					    )
					  )
					, array('id'=>'primaryActions', 'style'=>'text-align:left', 'data-bind'=>'css: $root.enable.save'))->split(); --}}				
			@else
				{{ Button::success(trans('texts.save_invoice'), array('id' => 'saveButton', 'onclick' => 'onSaveClick()')) }}			
			@endif

			{{ Button::normal(trans('texts.email_invoice'), array('id' => 'email_button', 'onclick' => 'onEmailClick()'))->append_with_icon('send'); }}		

			@if ($invoice)		
				{{ Button::primary(trans('texts.enter_payment'), array('onclick' => 'onPaymentClick()'))->append_with_icon('usd'); }}		
			@endif
		@endif

	</div>
	<p>&nbsp;</p>
	
	<!-- <textarea rows="20" cols="120" id="pdfText" onkeyup="runCode()"></textarea> -->
	<!-- <iframe frameborder="1" width="100%" height="600" style="display:block;margin: 0 auto"></iframe>	-->
	<iframe id="theFrame" style="display:none" frameborder="1" width="100%" height="1180"></iframe>
	<canvas id="theCanvas" style="display:none;width:100%;border:solid 1px #CCCCCC;"></canvas>

	@if (!Auth::user()->account->isPro())
		<div style="font-size:larger">
			{{ trans('texts.pro_plan.remove_logo', ['link'=>'<a href="#" onclick="showProPlan()">'.trans('texts.pro_plan.remove_logo_link').'</a>']) }}
		</div>
	@endif

	<div class="modal fade" id="clientModal" tabindex="-1" role="dialog" aria-labelledby="clientModalLabel" aria-hidden="true">
	  <div class="modal-dialog" style="min-width:1000px">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	        <h4 class="modal-title" id="clientModalLabel">{{ trans('texts.client') }}</h4>
	      </div>

	      <div class="container" style="width: 100%">
		<div style="background-color: #fff" class="row" data-bind="with: client" onkeypress="clientModalEnterClick(event)">
			<div class="col-md-6" style="margin-left:0px;margin-right:0px" >

				{{ Former::legend('organization') }}
				{{ Former::text('name')->data_bind("value: name, valueUpdate: 'afterkeydown', attr { placeholder: name.placeholder }") }}
				{{ Former::text('website')->data_bind("value: website, valueUpdate: 'afterkeydown'") }}
				{{ Former::text('work_phone')->data_bind("value: work_phone, valueUpdate: 'afterkeydown'") }}

				@if (Auth::user()->isPro())				
					@if ($account->custom_client_label1)
						{{ Former::text('custom_value1')->label($account->custom_client_label1)
							->data_bind("value: custom_value1, valueUpdate: 'afterkeydown'") }}
					@endif
					@if ($account->custom_client_label2)
						{{ Former::text('custom_value2')->label($account->custom_client_label2)
							->data_bind("value: custom_value2, valueUpdate: 'afterkeydown'") }}
					@endif
				@endif				
				
				{{ Former::legend('address') }}
				{{ Former::text('address1')->data_bind("value: address1, valueUpdate: 'afterkeydown'") }}
				{{ Former::text('address2')->data_bind("value: address2, valueUpdate: 'afterkeydown'") }}
				{{ Former::text('city')->data_bind("value: city, valueUpdate: 'afterkeydown'") }}
				{{ Former::text('state')->data_bind("value: state, valueUpdate: 'afterkeydown'") }}
				{{ Former::text('postal_code')->data_bind("value: postal_code, valueUpdate: 'afterkeydown'") }}
				{{ Former::select('country_id')->addOption('','')->addGroupClass('country_select')
					->fromQuery($countries, 'name', 'id')->data_bind("dropdown: country_id") }}
					
			</div>
			<div class="col-md-6" style="margin-left:0px;margin-right:0px" >


				{{ Former::legend('contacts') }}
				<div data-bind='template: { foreach: contacts,
			                            beforeRemove: hideContact,
			                            afterAdd: showContact }'>
					{{ Former::hidden('public_id')->data_bind("value: public_id, valueUpdate: 'afterkeydown'") }}
					{{ Former::text('first_name')->data_bind("value: first_name, valueUpdate: 'afterkeydown'") }}
					{{ Former::text('last_name')->data_bind("value: last_name, valueUpdate: 'afterkeydown'") }}
					{{ Former::text('email')->data_bind('value: email, valueUpdate: \'afterkeydown\', attr: {id:\'email\'+$index()}') }}
					{{ Former::text('phone')->data_bind("value: phone, valueUpdate: 'afterkeydown'") }}	

					<div class="form-group">
						<div class="col-lg-8 col-lg-offset-4">
							<span class="redlink bold" data-bind="visible: $parent.contacts().length > 1">
								{{ link_to('#', trans('texts.remove_contact').' -', array('data-bind'=>'click: $parent.removeContact')) }}
							</span>					
							<span data-bind="visible: $index() === ($parent.contacts().length - 1)" class="pull-right greenlink bold">
								{{ link_to('#', trans('texts.add_contact').' +', array('data-bind'=>'click: $parent.addContact')) }}
							</span>
						</div>
					</div>
				</div>

				{{ Former::legend('additional_info') }}
				{{ Former::select('payment_terms')->addOption('','0')->data_bind('value: payment_terms')
					->fromQuery($paymentTerms, 'name', 'num_days') }}
				{{ Former::select('currency_id')->addOption('','')->data_bind('value: currency_id')
					->fromQuery($currencies, 'name', 'id') }}
				{{ Former::select('size_id')->addOption('','')->data_bind('value: size_id')
					->fromQuery($sizes, 'name', 'id') }}
				{{ Former::select('industry_id')->addOption('','')->data_bind('value: industry_id')
					->fromQuery($industries, 'name', 'id') }}
				{{ Former::textarea('private_notes')->data_bind('value: private_notes') }}


			</div>
		</div>
		</div>

	     <div class="modal-footer" style="margin-top: 0px">
	      	<span class="error-block" id="emailError" style="display:none;float:left;font-weight:bold">{{ trans('texts.provide_email') }}</span><span>&nbsp;</span>
	      	<button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.cancel') }}</button>
	        <button id="clientDoneButton" type="button" class="btn btn-primary" data-bind="click: $root.clientFormComplete">{{ trans('texts.done') }}</button>	      	
	     </div>
	  		
	    </div>
	  </div>
	</div>

	<div class="modal fade" id="taxModal" tabindex="-1" role="dialog" aria-labelledby="taxModalLabel" aria-hidden="true">
	  <div class="modal-dialog" style="min-width:150px">
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
			            	<input onkeyup="onTaxRateChange()" data-bind="value: name, valueUpdate: 'afterkeydown'" class="form-control" onchange="refreshPDF()"//>			            	
			            </td>
			            <td style="width:60px">
			            	<input onkeyup="onTaxRateChange()" data-bind="value: prettyRate, valueUpdate: 'afterkeydown'" style="text-align: right" class="form-control" onchange="refreshPDF()"//>
			            </td>
			        	<td style="width:30px; cursor:pointer" class="hide-border td-icon">
			        		&nbsp;<i style="width:12px;" data-bind="click: $root.removeTaxRate, visible: actionsVisible() &amp;&amp; !isEmpty()" class="fa fa-minus-circle redlink" title="Remove item"/>
			        	</td>
			        </tr>
				</tbody>
			</table>
			&nbsp;

			{{ Former::checkbox('invoice_taxes')->text(trans('texts.enable_invoice_tax'))
				->label(trans('texts.settings'))->data_bind('checked: $root.invoice_taxes, enable: $root.tax_rates().length > 1') }}
			{{ Former::checkbox('invoice_item_taxes')->text(trans('texts.enable_line_item_tax'))
				->label('&nbsp;')->data_bind('checked: $root.invoice_item_taxes, enable: $root.tax_rates().length > 1') }}

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
	    	&nbsp; {{ trans('texts.recurring_help') }} &nbsp;
		</div>

	     <div class="modal-footer" style="margin-top: 0px">
	      	<button type="button" class="btn btn-primary" data-dismiss="modal">{{ trans('texts.close') }}</button>
	     </div>
	  		
	    </div>
	  </div>
	</div>

	{{ Former::close() }}


	</div>

	<script type="text/javascript">
	
	function showSignUp() {
		$('#signUpModal').modal('show');		
	}

	function showLearnMore() {
		$('#recurringModal').modal('show');			
	}

	$(function() {

		$('#country_id').combobox().on('change', function(e) {
			var countryId = parseInt($('input[name=country_id]').val(), 10);	
			var foundMatch = false;
			$('#country_id option').each(function() {
				var itemId = parseInt($(this).val(), 10);					
				if (countryId === itemId) {
					foundMatch = true;
					var country = {id:countryId, name:$(this).text()};
					model.invoice().client().country = country;
					model.invoice().client().country_id(countryId);
					return;					
				}
			});
			if (!foundMatch) {
				model.invoice().client().country = false;
				model.invoice().client().country_id(0);
			}
		});

		$('[rel=tooltip]').tooltip();

		$('#invoice_date, #due_date, #start_date, #end_date').datepicker();

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
			} else {
				model.loadClient($.parseJSON(ko.toJSON(new ClientModel())));
				model.invoice().client().country = false;				
			}
			refreshPDF();
		}); //.trigger('change');						

		$('#terms, #public_notes, #invoice_number, #invoice_date, #due_date, #po_number, #discount, #currency_id, #invoice_design_id').change(function() {
			refreshPDF();
		});

		@if ($client || $invoice)
			$('#invoice_number').focus();
		@else
			$('.client_select input.form-control').focus();			
		@endif
		
		$('#clientModal').on('shown.bs.modal', function () {
			$('#name').focus();			
		}).on('hidden.bs.modal', function () {
			if (model.clientBackup) {
				model.loadClient(model.clientBackup);
				refreshPDF();
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

		$('#primaryActions > button:first').click(function() {
			onSaveClick();
		});

		$('label.radio').addClass('radio-inline');

		applyComboboxListeners();
		
		@if ($client)
			$input.trigger('change');
		@else 
			refreshPDF();
		@endif

		var client = model.invoice().client();
		setComboboxValue($('.client_select'), 
			client.public_id(), 
			client.name.display());
		
	});	

	function applyComboboxListeners() {
		var selectorStr = '.invoice-table input, .invoice-table select, .invoice-table textarea';		
		$(selectorStr).off('blur').on('blur', function() {
			refreshPDF();
		});

		@if (Auth::user()->account->fill_products)
			$('.datalist').on('input', function() {			
				var key = $(this).val();
				for (var i=0; i<products.length; i++) {
					var product = products[i];
					if (product.product_key == key) {
						var model = ko.dataFor(this);					
						model.notes(product.notes);
						model.cost(accounting.toFixed(product.cost,2));
						//model.qty(product.qty);
						break;
					}
				}
			});
		@endif
	}

	function createInvoiceModel() {
		var invoice = ko.toJS(model).invoice;		
		invoice.is_pro = {{ Auth::user()->isPro() ? 'true' : 'false' }};

		@if (file_exists($account->getLogoPath()))
			invoice.image = "{{ HTML::image_data($account->getLogoPath()) }}";
			invoice.imageWidth = {{ $account->getLogoWidth() }};
			invoice.imageHeight = {{ $account->getLogoHeight() }};
		@endif

		window.logoImages = {};
    logoImages.imageLogo1 = "{{ HTML::image_data('images/report_logo1.jpg') }}";
		logoImages.imageLogoWidth1 =120;
    logoImages.imageLogoHeight1 = 40

    logoImages.imageLogo2 = "{{ HTML::image_data('images/report_logo2.jpg') }}";
    logoImages.imageLogoWidth2 =325/2;
    logoImages.imageLogoHeight2 = 81/2;

    logoImages.imageLogo3 = "{{ HTML::image_data('images/report_logo3.jpg') }}";
    logoImages.imageLogoWidth3 =325/2;
    logoImages.imageLogoHeight3 = 81/2;


    return invoice;
	}

	function toggleDatePicker(field) {
		$('#'+field).datepicker('show');
	}

	/*
	function refreshPDF() {
		setTimeout(function() {
			_refreshPDF();
		}, 100);
	}	
	*/

	var isRefreshing = false;
	var needsRefresh = false;

	function getPDFString() {		
		var invoice = createInvoiceModel();
		var doc = generatePDF(invoice);
		if (!doc) return;
		return doc.output('datauristring');
	}

	function refreshPDF() {
		if ({{ Auth::user()->force_pdfjs ? 'false' : 'true' }} && (isFirefox || (isChrome && !isChromium))) {
			var string = getPDFString();
			if (!string) return;
			$('#theFrame').attr('src', string).show();		
		} else {			
			if (isRefreshing) {
				needsRefresh = true;
				return;
			}
			var string = getPDFString();
			if (!string) return;
			isRefreshing = true;
			var pdfAsArray = convertDataURIToBinary(string);	
	    PDFJS.getDocument(pdfAsArray).then(function getPdfHelloWorld(pdf) {

	      pdf.getPage(1).then(function getPageHelloWorld(page) {
	        var scale = 1.5;
	        var viewport = page.getViewport(scale);

	        var canvas = document.getElementById('theCanvas');
	        var context = canvas.getContext('2d');
	        canvas.height = viewport.height;
	        canvas.width = viewport.width;

	        page.render({canvasContext: context, viewport: viewport});
	      	$('#theCanvas').show();
	      	isRefreshing = false;
	      	if (needsRefresh) {
	      		needsRefresh = false;
	      		refreshPDF();
	      	}
	      });
	    });	
		}
	}

	function onDownloadClick() {
		var invoice = createInvoiceModel();
		var doc = generatePDF(invoice, true);
		doc.save('Invoice-' + $('#invoice_number').val() + '.pdf');
	}

	function onEmailClick() {
		@if (Auth::user()->confirmed)
		if (confirm('Are you sure you want to email this invoice?')) {
			$('#action').val('email');
			$('#submitButton').click();
		}
		@else
			$('#action').val('email');
			$('#submitButton').click();
		@endif
	}

	function onSaveClick() {
		$('#action').val('');
		$('#submitButton').click();
	}

	function isSaveValid() {
		var isValid = false;
		for (var i=0; i<self.invoice().client().contacts().length; i++) {
			var contact = self.invoice().client().contacts()[i];
			if (isValidEmailAddress(contact.email())) {
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

	function onCloneClick() {
		$('#action').val('clone');
		$('#submitButton').click();
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
		$('#action').val('archive');
		$('#submitButton').click();		
	}

	function onDeleteClick() {
		if (confirm('Are you sure you want to delete this invoice?')) {
			$('#action').val('delete');
			$('#submitButton').click();			
		}		
	}

	function formEnterClick(event) {
		if (event.keyCode === 13){
			if (event.target.type == 'textarea') {
				return;
			}
			event.preventDefault();		     				

			$('#action').val('');
			$('#submitButton').click();
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
		//self.invoice = data ? false : new InvoiceModel();
		self.invoice = ko.observable(data ? false : new InvoiceModel());
		self.tax_rates = ko.observableArray();

		self.loadClient = function(client) {
			ko.mapping.fromJS(client, model.invoice().client().mapping, model.invoice().client);
			self.setDueDate();
		}

		self.setDueDate = function() {
			var paymentTerms = parseInt(self.invoice().client().payment_terms());
			if (paymentTerms && !self.invoice().due_date())
			{
				var dueDate = $('#invoice_date').datepicker('getDate');
				dueDate.setDate(dueDate.getDate() + paymentTerms);
				self.invoice().due_date(dueDate);	
				// We're using the datepicker to handle the date formatting 
				self.invoice().due_date($('#due_date').val());
			}			
		}

		self.invoice_taxes = ko.observable({{ Auth::user()->account->invoice_taxes ? 'true' : 'false' }});
		self.invoice_item_taxes = ko.observable({{ Auth::user()->account->invoice_item_taxes ? 'true' : 'false' }});
		
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
			if (parseFloat(rate) > 0) taxRate.is_deleted(true);
			self.tax_rates.push(taxRate);
			return taxRate;			
		}		

		self.showTaxesForm = function() {
			self.taxBackup = ko.mapping.toJS(self.tax_rates);

			$('#taxModal').modal('show');	
		}	

		self.taxFormComplete = function() {
			model.taxBackup = false;
			$('#taxModal').modal('hide');	
		}

		self.showClientForm = function() {
			self.clientBackup = ko.mapping.toJS(self.invoice().client);

			$('#emailError').css( "display", "none" );			
			$('#clientModal').modal('show');			
		}

		self.clientFormComplete = function() {

			var isValid = true;
			$("input[name='email']").each(function(item, value) {
				var email = $(value).val();
				if (!email || !isValidEmailAddress(email)) {
					isValid = false;					
				}
			});
			if (!isValid) {
				$('#emailError').css( "display", "inline" );
				return;
			}

			var email = $('#email0').val();
			var firstName = $('#first_name').val();
			var lastName = $('#last_name').val();
			var name = $('#name').val();

			if (self.invoice().client().public_id() == 0) {
				self.invoice().client().public_id(-1);
			}

			model.setDueDate();

			if (name) {
				//
			} else if (firstName || lastName) {
				name = firstName + ' ' + lastName;
			} else {
				name = email;
			}

			setComboboxValue($('.client_select'), -1, name);

			//$('.client_select select').combobox('setSelected');
			//$('.client_select input.form-control').val(name);
			//$('.client_select .combobox-container').addClass('combobox-selected');

			$('#emailError').css( "display", "none" );
			//$('.client_select input.form-control').focus();						

			refreshPDF();
			model.clientBackup = false;
			$('#clientModal').modal('hide');			

			$('#invoice_number').focus();
		}		

		self.clientLinkText = ko.computed(function() {
			if (self.invoice().client().public_id())
			{
				return "{{ trans('texts.edit_client_details') }}";
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
		self.account = {{ $account }};		
		this.id = ko.observable('');
		self.discount = ko.observable('');
		self.frequency_id = ko.observable('');
		//self.currency_id = ko.observable({{ $client && $client->currency_id ? $client->currency_id : Session::get(SESSION_CURRENCY) }});
		self.terms = ko.observable(wordWrapText('{{ str_replace(["\r\n","\r","\n"], '\n', addslashes($account->invoice_terms)) }}', 300));
		self.set_default_terms = ko.observable(false);
		self.public_notes = ko.observable('');		
		self.po_number = ko.observable('');
		self.invoice_date = ko.observable('{{ Utils::today() }}');
		self.invoice_number = ko.observable('{{ isset($invoiceNumber) ? $invoiceNumber : '' }}');
		self.due_date = ko.observable('');
		self.start_date = ko.observable('{{ Utils::today() }}');
		self.end_date = ko.observable('');
		self.tax_name = ko.observable();
		self.tax_rate = ko.observable();
		self.is_recurring = ko.observable(false);
		self.invoice_status_id = ko.observable(0);
		self.invoice_items = ko.observableArray();
		self.amount = ko.observable(0);
		self.balance = ko.observable(0);
		self.invoice_design_id = ko.observable({{ $account->invoice_design_id }});

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
			self.invoice_items.push(itemModel);	
			applyComboboxListeners();			
		}

		if (data) {
			ko.mapping.fromJS(data, self.mapping, self);			
			self.is_recurring(parseInt(data.is_recurring));
		} else {
			self.addItem();
		}

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
				$('#terms').height(this.terms().split('\n').length * 36);
				return this.terms();
			},
			write: function(value) {
				value = wordWrapText(value, 300);
				self.terms(value);
				$('#terms').height(value.split('\n').length * 36);
			},
			owner: this
		});


		self.wrapped_notes = ko.computed({
			read: function() {
				$('#public_notes').height(this.public_notes().split('\n').length * 36);
				return this.public_notes();
			},
			write: function(value) {
				value = wordWrapText(value, 300);
				self.public_notes(value);
				$('#public_notes').height(value.split('\n').length * 36);
			},
			owner: this
		});


		self.removeItem = function(item) {
			self.invoice_items.remove(item);
			refreshPDF();
		}


		this.totals = ko.observable();

		this.totals.rawSubtotal = ko.computed(function() {
		    var total = 0;
		    for(var p=0; p < self.invoice_items().length; ++p) {
		    	var item = self.invoice_items()[p];
		        total += item.totals.rawTotal();
		    }
		    return total;
		});

		this.totals.subtotal = ko.computed(function() {
		    var total = self.totals.rawSubtotal();
		    return total > 0 ? formatMoney(total, self.client().currency_id()) : '';
		});

		this.totals.rawDiscounted = ko.computed(function() {
			return self.totals.rawSubtotal() * (self.discount()/100);			
		});

		this.totals.discounted = ko.computed(function() {
			return formatMoney(self.totals.rawDiscounted(), self.client().currency_id());
		});

		self.totals.taxAmount = ko.computed(function() {
		    var total = self.totals.rawSubtotal();

		    var discount = parseFloat(self.discount());
		    if (discount > 0) {
		    	total = total * ((100 - discount)/100);
		    }

			var taxRate = parseFloat(self.tax_rate());
			if (taxRate > 0) {
				var tax = total * (taxRate/100);			
        		return formatMoney(tax, self.client().currency_id());
        	} else {
        		return formatMoney(0);
        	}
    	});

		this.totals.rawPaidToDate = ko.computed(function() {
			return accounting.toFixed(self.amount(),2) - accounting.toFixed(self.balance(),2);		    
		});

		this.totals.paidToDate = ko.computed(function() {
			var total = self.totals.rawPaidToDate();
		    return total > 0 ? formatMoney(total, self.client().currency_id()) : '';			
		});

		this.totals.total = ko.computed(function() {
		    var total = accounting.toFixed(self.totals.rawSubtotal(),2);

		    var discount = parseFloat(self.discount());
		    if (discount > 0) {
		    	total = total * ((100 - discount)/100);
		    }

			var taxRate = parseFloat(self.tax_rate());
			if (taxRate > 0) {
        		total = NINJA.parseFloat(total) + (total * (taxRate/100));
        	}        	

        	var paid = self.totals.rawPaidToDate();
        	if (paid > 0) {
        		total -= paid;
        	}

		    return total != 0 ? formatMoney(total, self.client().currency_id()) : '';
    	});

    	self.onDragged = function(item) {
    		refreshPDF();
    	}	
	}

	function ClientModel(data) {
		var self = this;
		self.public_id = ko.observable(0);
		self.name = ko.observable('');
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
		self.website = ko.observable('');
		self.payment_terms = ko.observable(0);
		self.contacts = ko.observableArray();

		self.mapping = {
	    	'contacts': {
	        	create: function(options) {
	            	return new ContactModel(options.data);
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

		self.email.display = ko.computed(function() {
			var str = '';
			if (self.first_name() || self.last_name()) {
				str += self.first_name() + ' ' + self.last_name() + '<br/>';
			}			
			str += self.email();

			@if (Utils::isConfirmed())
			if (self.invitation_link()) {
				str += '<br/><a href="' + self.invitation_link() + '" target="_blank">{{ trans('texts.view_as_recipient') }}</a>';
			}
			@endif
			
			return str;
		});		
		
		if (data) {
			ko.mapping.fromJS(data, {}, this);		
		}		
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
	            return this.rate() ? parseFloat(this.rate()) : '';
	        },
	        write: function (value) {
	            this.rate(value);
	        },
	        owner: this
	    });				


		self.displayName = ko.computed({
			read: function () {
				var name = self.name() ? self.name() : '';
				var rate = self.rate() ? parseFloat(self.rate()) + '% ' : '';
				return rate + name;
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
		this.product_key = ko.observable('');
		this.notes = ko.observable('');
		this.cost = ko.observable(0);
		this.qty = ko.observable(0);
		self.tax_name = ko.observable('');
		self.tax_rate = ko.observable(0);
		this.actionsVisible = ko.observable(false);
		
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
			//if (this.cost()) this.cost(formatMoney(this.cost(), model ? model.invoice().currency_id() : 1, true));
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
			var cost = NINJA.parseFloat(self.cost());
			var qty = NINJA.parseFloat(self.qty());
			var taxRate = NINJA.parseFloat(self.tax_rate());
        	var value = cost * qty;        	
        	if (taxRate > 0) {
        		value += value * (taxRate/100);
        	}        	
        	return value ? value : '';
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
    		return !self.product_key() && !self.notes() && !self.cost() && !self.qty();
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

		$('.word-wrap').each(function(index, input) {
			$(input).height($(input).val().split('\n').length * 20);
		});
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

	var products = {{ $products }};
	var clients = {{ $clients }};	
	var invoiceLabels = {{ json_encode($invoiceLabels) }};
	var clientMap = {};
	var $clientSelect = $('select#client');
	
	for (var i=0; i<clients.length; i++) {
		var client = clients[i];
		for (var j=0; j<client.contacts.length; j++) {
			var contact = client.contacts[j];
			contact.send_invoice = contact.is_primary;
		}
		clientMap[client.public_id] = client;
		$clientSelect.append(new Option(getClientDisplayName(client), client.public_id)); 
	}

	@if ($data)
		window.model = new ViewModel({{ $data }});				
	@else 
		window.model = new ViewModel();
		model.addTaxRate();
		@foreach ($taxRates as $taxRate)
			model.addTaxRate({{ $taxRate }});
		@endforeach	
		@if ($invoice)
			var invoice = {{ $invoice }};
			ko.mapping.fromJS(invoice, model.invoice().mapping, model.invoice);			
			if (model.invoice().is_recurring() === '0') {
				model.invoice().is_recurring(false);
			}
			var invitationContactIds = {{ json_encode($invitationContactIds) }};		
			var client = clientMap[invoice.client.public_id];
			for (var i=0; i<client.contacts.length; i++) {
				var contact = client.contacts[i];
				contact.send_invoice = invitationContactIds.indexOf(contact.public_id) >= 0;
			}			
			model.invoice().addItem();
			//model.addTaxRate();			
		@endif
	@endif

	model.invoice().tax(model.getTaxRate(model.invoice().tax_name(), model.invoice().tax_rate()));			
	for (var i=0; i<model.invoice().invoice_items().length; i++) {
		var item = model.invoice().invoice_items()[i];
		item.tax(model.getTaxRate(item.tax_name(), item.tax_rate()));
		item.cost(NINJA.parseFloat(item.cost()) > 0 ? formatMoney(item.cost(), model.invoice().client().currency_id(), true) : '');
	}
	onTaxRateChange();

	if (!model.invoice().discount()) model.invoice().discount('');

	ko.applyBindings(model);	
	onItemChange();

	</script>

@stop
