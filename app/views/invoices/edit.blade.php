@extends('header')

@section('head')
	@parent

		<script type="text/javascript" src="{{ asset('js/pdf_viewer.js') }}"></script>
		<script type="text/javascript" src="{{ asset('js/compatibility.js') }}"></script>
@stop

@section('content')
	
	<p>&nbsp;</p>

	{{ Former::open($url)->method($method)->addClass('main_form')->rules(array(
		'client' => 'required',
		'product_key' => 'max:14',
	)); }}

	<!-- <h3>{{ $title }} Invoice</h3> -->

	@if ($invoice)
		{{ Former::populate($invoice); }}
		{{ Former::populateField('id', $invoice->public_id); }}	
		{{ Former::populateField('invoice_date', Utils::fromSqlDate($invoice->invoice_date)); }}	
		{{ Former::populateField('due_date', Utils::fromSqlDate($invoice->due_date)); }}
		{{ Former::populateField('start_date', Utils::fromSqlDate($invoice->start_date)); }}
		{{ Former::populateField('end_date', Utils::fromSqlDate($invoice->end_date)); }}		
	@else
		{{ Former::populateField('invoice_number', $invoiceNumber) }}
		{{ Former::populateField('invoice_date', date('m/d/Y')) }}
		{{ Former::populateField('start_date', date('m/d/Y')) }}
		{{ Former::populateField('frequency', FREQUENCY_MONTHLY) }}
	@endif
    
    <div class="row" style="min-height:195px">
    	<div class="col-md-7" id="col_1">
			{{ Former::select('client')->addOption('', '')->fromQuery($clients, 'name', 'public_id')->select($client ? $client->public_id : '')->addGroupClass('client_select')
				->help('<a style="cursor:pointer" data-toggle="modal" id="modalLink" onclick="showCreateNew()">Create new client</a>') }}
			{{ Former::text('discount')->data_bind("value: discount, valueUpdate: 'afterkeydown'") }}
			{{ Former::textarea('notes') }}			
			
		</div>
		<div class="col-md-4" id="col_2">
			<div id="recurring_checkbox">
				{{ Former::checkbox('recurring')->text('Enable | <a href="#">Learn more</a>')->onchange('toggleRecurring()')
					->inlineHelp($invoice && $invoice->last_sent_date ? 'Last invoice sent ' . Utils::timestampToDateString($invoice->last_sent_date) : '') }}
			</div>
			<div id="recurring_off">
				{{ Former::text('invoice_number')->label('Invoice #') }}
				{{ Former::text('po_number')->label('PO number') }}
				{{ Former::text('invoice_date') }}
				{{ Former::text('due_date') }}
			
				
				{{-- Former::text('invoice_date')->label('Invoice Date')->data_date_format('yyyy-mm-dd') --}}	
			</div>
			<div id="recurring_on" style="display:none">
				{{ Former::select('frequency')->label('How often')->options($frequencies)->onchange('updateRecurringStats()') }}
				{{ Former::text('start_date')->onchange('updateRecurringStats()') }}
				{{ Former::text('end_date')->onchange('updateRecurringStats()') }}
			</div>
			
		</div>
		<div class="col-md-3" id="col_3" style="display:none">			


		</div>
	</div>

	<p>&nbsp;</p>

	{{ Former::hidden('items')->data_bind("value: ko.toJSON(items)") }}	

	<table class="table invoice-table" style="margin-bottom: 0px !important">
	    <thead>
	        <tr>
	        	<th class="hide-border"></th>
	        	<th>Item</th>
	        	<th>Description</th>
	        	<th>Rate</th>
	        	<th>Units</th>
	        	<th>Line&nbsp;Total</th>
	        	<th class="hide-border"></th>
	        </tr>
	    </thead>
	    <tbody data-bind="sortable: { data: items, afterMove: onDragged }">
	    	<tr data-bind="event: { mouseover: showActions, mouseout: hideActions }" class="sortable-row">
	        	<td style="width:20px;" class="hide-border td-icon">
	        		<i data-bind="visible: actionsVisible() &amp;&amp; $parent.items().length > 1" class="fa fa-sort"></i>
	        	</td>
	            <td style="width:120px">	            	
	            	{{ Former::text('product_key')->useDatalist(Product::getProductKeys($products), 'key')->onkeyup('onChange()')
	            		->raw()->data_bind("value: product_key, valueUpdate: 'afterkeydown'")->addClass('datalist') }}
	            </td>
	            <td style="width:300px">
	            	<textarea onkeyup="checkWordWrap(event)" data-bind="value: notes, valueUpdate: 'afterkeydown'" rows="1" cols="60" style="resize: none;" class="form-control" onchange="refreshPDF()"></textarea>
	            </td>
	            <td style="width:100px">
	            	<input onkeyup="onChange()" data-bind="value: cost, valueUpdate: 'afterkeydown'" style="text-align: right" class="form-control" onchange="refreshPDF()"//>
	            </td>
	            <td style="width:80px">
	            	<input onkeyup="onChange()" data-bind="value: qty, valueUpdate: 'afterkeydown'" style="text-align: right" class="form-control" onchange="refreshPDF()"//>
	            </td>
	            <!--
	            <td style="width:100px">
	            	<input data-bind="value: tax, valueUpdate: 'afterkeydown'"/>
	            </td>
	        	-->
	            <td style="width:100px;text-align: right;padding-top:9px !important">
	            	<span data-bind="text: total"></span>
	            </td>
	        	<td style="width:20px; cursor:pointer" class="hide-border td-icon">
	        		&nbsp;<i data-bind="click: $parent.removeItem, visible: actionsVisible() &amp;&amp; $parent.items().length > 1" class="fa fa-minus-circle" title="Remove item"/>
	        	</td>
	        </tr>
		</tbody>
		<tfoot>	        
	        <tr>
	        	<td class="hide-border"></td>
	        	<td colspan="2"/>
				<td colspan="2">Subtotal</td>
				<td style="text-align: right"><span data-bind="text: subtotal"/></td>
	        </tr>
	        <tr>
	        	<td class="hide-border"></td>
	        	<td colspan="2" class="hide-border"/>
				<td colspan="2">Paid to Date</td>
				<td style="text-align: right"></td>
	        </tr>	        
	        <tr data-bind="visible: discount() > 0">
	        	<td class="hide-border"></td>
	        	<td colspan="2" class="hide-border"/>
				<td colspan="2">Discount</td>
				<td style="text-align: right"><span data-bind="text: discounted"/></td>
	        </tr>
	        <tr>
	        	<td class="hide-border"></td>
	        	<td colspan="2" class="hide-border"/>
				<td colspan="2"><b>Balance Due</b></td>
				<td style="text-align: right"><span data-bind="text: total"/></td>
	        </tr>
	    </tfoot>
	</table>

	<p>&nbsp;</p>
	<div class="form-actions">

		<div style="display:none">
			{{ Former::text('action') }}
			@if ($invoice)		
				{{ Former::text('id') }}
			@endif
		</div>


		{{ Button::normal('Download PDF', array('onclick' => 'onDownloadClick()')) }}	

		@if ($invoice)		
			{{ DropdownButton::primary('Save Invoice',
				  Navigation::links(
				    array(
				    	array('Save Invoice', "javascript:onSaveClick()"),
				     	array('Clone Invoice', "javascript:onCloneClick()"),
				     	array(Navigation::DIVIDER),
				     	array('Archive Invoice', "javascript:onArchiveClick()"),
				     	array('Delete Invoice', "javascript:onDeleteClick()"),
				    )
				  )
				, array('id'=>'actionDropDown','style'=>'text-align:left'))->split(); }}				
		@else
			{{ Button::primary_submit('Save Invoice') }}			
		@endif

		{{ Button::primary('Send Email', array('id' => 'email_button', 'onclick' => 'onEmailClick()')) }}		
	</div>
	<p>&nbsp;</p>
	
	<!-- <textarea rows="20" cols="120" id="pdfText" onkeyup="runCode()"></textarea> -->
	<!-- <iframe frameborder="1" width="100%" height="600" style="display:block;margin: 0 auto"></iframe>	-->
	<iframe id="theIFrame" frameborder="1" width="100%" height="500"></iframe>
	<canvas id="theCanvas" style="display:none;width:100%;border:solid 1px #CCCCCC;"></canvas>


	<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	  <div class="modal-dialog" style="min-width:1000px">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	        <h4 class="modal-title" id="myModalLabel">New Client</h4>
	      </div>

		<div class="row" style="padding-left:16px;padding-right:16px" onkeypress="preventFormSubmit(event)">
			<div class="col-md-6">	    

      		{{ Former::legend('Organization') }}
			{{ Former::text('name') }}
			{{ Former::text('work_phone')->label('Phone') }}

			{{ Former::legend('Contact') }}
			{{ Former::text('first_name') }}
			{{ Former::text('last_name') }}
			{{ Former::text('email') }}
			{{ Former::text('phone') }}	

			</div>
			<div class="col-md-6">	    			
      		{{ Former::legend('Address') }}
			{{ Former::text('address1')->label('Street') }}
			{{ Former::text('address2')->label('Apt/Floor') }}
			{{ Former::text('city') }}
			{{ Former::text('state') }}
			{{ Former::text('postal_code') }}
			{{ Former::select('country_id')->addOption('','')->label('Country')->addGroupClass('country_select')
				->fromQuery($countries, 'name', 'id')->select($client ? $client->country_id : '') }}
			</div>
		</div>

	      <!--
	      <div class="modal-body" style="min-height:80px">
	      	<div class="form-group">
	      		<label for="name" class="control-label col-lg-2 col-sm-4">Name<sup>*</sup></label>
	      		<div class="col-lg-10 col-sm-8">
	      			<input class="form-control" id="client_name" type="text" name="client_name" onkeypress="nameKeyPress(event)">
	      			<span class="help-block" id="nameHelp" style="display: none">Please provide a value</span><span>&nbsp;</span>
	      		</div>	      		
	      	</div>
	      	<div class="form-group">
	      		<label for="email" class="control-label col-lg-2 col-sm-4">Email<sup>*</sup></label>
	      		<div class="col-lg-10 col-sm-8">
	      			<input class="form-control" id="client_email" type="text" name="client_email" onkeypress="nameKeyPress(event)">
	      			<span class="help-block" id="emailHelp" style="display: none">Please provide a value</span><span>&nbsp;</span>
	      		</div>	      		
	      	</div>
	      </div>
	  		-->

	      <div class="modal-footer">
	      	<span class="error-block" id="nameError" style="display:none;float:left">Please provide a value for the name field.</span><span>&nbsp;</span>
	      	<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
	        <button type="button" class="btn btn-primary" onclick="newClient()">Done</button>	      	
	      </div>
	  		
	    </div>
	  </div>
	</div>

	{{ Former::close() }}

	<script type="text/javascript">

	/*
	function textarea_height(TextArea, MaxHeight) {
	    textarea = document.getElementById(TextArea);
	    textareaRows = textarea.value.split("\n");
	    if(textareaRows[0] != "undefined" && textareaRows.length < MaxHeight) counter = textareaRows.length;
	    else if(textareaRows.length >= MaxHeight) counter = MaxHeight;
	    else counter = 1;
	    textarea.rows = counter; 
	}
	*/
	

	$(function() {

		$('form').change(refreshPDF);

		$('#country_id').combobox();

		$('#invoice_date').datepicker({
			autoclose: true,
			todayHighlight: true
		});

		$('#due_date, #start_date, #end_date').datepicker({
			autoclose: true,
			todayHighlight: true
		});

		var $input = $('select#client');
		$input.combobox();
		$('.client_select input.form-control').on('change', function(e) {
			var clientId = parseInt($('input[name=client]').val(), 10);	
			$('#modalLink').text(clientId ? 'Edit client details' : 'Create new client');
			if (clientId > 0) {
				loadClientDetails(clientId);
			}
		}).trigger('change');

		//enableHoverClick($('.combobox-container input.form-control'), $('.combobox-container input[name=client]'), '{{ URL::to('clients') }}');

		@if ($client)
			$('#invoice_number').focus();
		@else
			//$('[name="client_combobox"]').focus();
		@endif
		
		/*
		$('#myModal').on('hidden.bs.modal', function () {
			$('#popup_client_name').val('');
		})
		*/

		$('#myModal').on('shown.bs.modal', function () {
			$('#name').focus();
		})

		$('#invoice_number').change(refreshPDF);

		$('#actionDropDown > button:first').click(function() {
			onSaveClick();
		});


		$('label.radio').addClass('radio-inline');
		
		
		@if ($invoice && $invoice->isRecurring())
			$('#recurring').prop('checked', true);
		@elseif (isset($invoice->recurring_invoice_id) && $invoice->recurring_invoice_id)
			$('#recurring_checkbox > div > div').html('Created by a {{ link_to('/invoices/'.$invoice->recurring_invoice_id, 'recurring invoice') }}').css('padding-top','6px');
		@elseif ($invoice && $invoice->isSent())
			$('#recurring_checkbox').hide();
		@endif
		
		toggleRecurring();	

		applyComboboxListeners();
		refreshPDF();		
	});

	function loadClientDetails(clientId) {
		var client = clientMap[clientId];
		$('#name').val(client.name);
		$('#work_phone').val(client.work_phone);
		$('#address1').val(client.address1);
		$('#address2').val(client.address2);
		$('#city').val(client.city);
		$('#state').val(client.state);
		$('#postal_code').val(client.postal_code);
		$('#country_id').val(client.country_id).combobox('refresh');
		for (var i=0; i<client.contacts.length; i++) {
			var contact = client.contacts[i];
			if (contact.is_primary) {
				$('#first_name').val(contact.first_name);
				$('#last_name').val(contact.last_name);
				$('#email').val(contact.email);
				$('#phone').val(contact.phone);
			}
		}
	}

	function showCreateNew() {		
		if (!$('input[name=client]').val()) {
			$('#myModal input').val('');
			$('#myModal #country_id').val('');
			$('#nameError').css( "display", "none" );			
		}
		
		$('#myModal').modal('show');	
	}


	function applyComboboxListeners() {
		var value;
		$('.datalist').on('focus', function() {
			value = $(this).val();
		}).on('blur', function() {
			if (value != $(this).val()) refreshPDF();
		}).on('input', function() {			
			var key = $(this).val();
			for (var i=0; i<products.length; i++) {
				var product = products[i];
				if (product.product_key == key) {
					var model = ko.dataFor(this);
					model.notes(product.notes);
					model.cost(product.cost);
					model.qty(product.qty);
					break;
				}
			}
		});
	}

	function runCode() {
		var text = $('#pdfText').val();
		eval(text);
	}

	function createInvoiceModel() {
		var invoice = {
			invoice_number: $('#invoice_number').val(),
			invoice_date: $('#invoice_date').val(),
			discount: parseFloat($('#discount').val()),
			po_number: $('#po_number').val(),
			account: {
				name: "{{ $account->name }}",
				address1: "{{ $account->address1 }}",
				address2: "{{ $account->address2 }}",
				city: "{{ $account->city }}",
				state: "{{ $account->state }}",
				postal_code: "{{ $account->postal_code }}",
				country: {
					name: "{{ $account->country ? $account->country->name : '' }}"
				}
			},
			@if (file_exists($account->getLogoPath()))
				image: "{{ HTML::image_data($account->getLogoPath()) }}",			
				imageWidth: {{ $account->getLogoWidth() }},
				imageHeight: {{ $account->getLogoHeight() }},
			@endif
			invoice_items: []
		};

		var client = {
			name: $('#name').val(),
			address1: $('#address1').val(),
			address2: $('#address2').val(),
			city: $('#city').val(),
			state: $('#state').val(),
			postal_code: $('#postal_code').val(),
			country: {
				name: $('.country_select input[type=text]').val()
			}
		};

		/*
		var clientId = $('input[name=client]').val();	
		if (clientId == '-1') {
			var client = {
				name: $('#name').val(),
				address1: $('#address1').val(),
				address2: $('#address2').val(),
				city: $('#city').val(),
				state: $('#state').val(),
				postal_code: $('#postal_code').val(),
				country: {
					name: $('.country_select input[type=text]').val()
				}
			};
		} else if (clientMap.hasOwnProperty(clientId)) {
			var client = clientMap[clientId];
		}
		*/
		invoice.client = client;

		for(var i=0; i<model.items().length; i++) {
			var item = model.items()[i];
			invoice.invoice_items.push({
				product_key: item.product_key(),
				notes: item.notes(),
				cost: item.cost(),
				qty: item.qty()
			});			
		}

		return invoice;
	}

	function refreshPDF() {
		setTimeout(function() {
			_refreshPDF();
		}, 100);
	}	


	function _refreshPDF() {
		var invoice = createInvoiceModel();
		var doc = generatePDF(invoice);		

		/*		
		var string = doc.output('dataurlstring');
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
	      });
	    });				
		*/

		var string = doc.output('datauristring');
		$('#theIFrame').attr('src', string);		
	}

	function onDownloadClick() {
		var invoice = createInvoiceModel();
		var doc = generatePDF(invoice);
		doc.save('Invoice-' + $('#invoice_number').val() + '.pdf');
	}

	function onEmailClick() {
		if (confirm('Are you sure you want to email this invoice?')) {
			$('#action').val('email');
			$('.main_form').submit();
		}
	}

	function onSaveClick() {
		$('.main_form').submit();
	}

	function onCloneClick() {
		$('#action').val('clone');
		$('.main_form').submit();
	}

	function onArchiveClick() {
		$('#action').val('archive');
		$('.main_form').submit();
	}

	function onDeleteClick() {
		if (confirm('Are you sure you want to delete this invoice?')) {
			$('#action').val('delete');
			$('.main_form').submit();
		}		
	}

	function newClient() {
		var name = $('#name').val();
		if (!name) {
			if (!name) $('#nameError').css( "display", "inline" );
		} else {
			$('select#client').combobox('setSelected');
			if (!$('input[name=client]').val()) {
				$('input[name=client]').val('-1');
			}
			$('.client_select input.form-control').val(name);
			$('.client_select .combobox-container').addClass('combobox-selected');

			$('#nameError').css( "display", "none" );
			$('#modalLink').text('Edit client details');
			$('#myModal').modal('hide');
			$('.client_select input.form-control').focus();			

			refreshPDF();
		}
	}		


	function preventFormSubmit(event) {		
		if (event.keyCode === 13){
			event.preventDefault();		     	
            newClient();
            return false;
        }
	}

	function InvoiceModel() {
		var self = this;
		self.discount = ko.observable();
		self.items = ko.observableArray();

		@if (isset($items) && $items)
			@foreach ($items as $item)
				var item = new ItemModel();
				item.product_key("{{ $item->product_key }}");
				item.notes('{{ str_replace("\n", "\\n", $item->notes) }}');
				item.cost("{{ isset($item->cost) ? $item->cost : '' }}");
				item.qty("{{ isset($item->qty) ? $item->qty : '' }}");
				self.items.push(item);								
			@endforeach
		@elseif ($invoice)
			self.discount({{ $invoice->discount }});
			@if (count($invoice->invoice_items) > 0)
				@foreach ($invoice->invoice_items as $item)
					var item = new ItemModel();
					item.product_key("{{ $item->product_key }}");
					item.notes('{{ str_replace("\n", "\\n", $item->notes) }}');
					item.cost("{{ $item->cost }}");
					item.qty("{{ $item->qty }}");				
					self.items.push(item);				
				@endforeach
			@endif
		@endif

		self.items.push(new ItemModel());
		
		self.removeItem = function(item) {
			self.items.remove(item);
			refreshPDF();
		}

		self.addItem = function() {
			self.items.push(new ItemModel());	
			applyComboboxListeners();
		}

		this.rawSubtotal = ko.computed(function() {
		    var total = 0;
		    for(var p = 0; p < self.items().length; ++p)
		    {
		        total += self.items()[p].rawTotal();
		    }
		    return total;
		});

		this.subtotal = ko.computed(function() {
		    var total = self.rawSubtotal();
		    return total > 0 ? formatMoney(total) : '';
		});


		this.discounted = ko.computed(function() {
			var total = self.rawSubtotal() * (self.discount()/100);
			return formatMoney(total);
		});

		this.total = ko.computed(function() {
		    var total = self.rawSubtotal();

		    var discount = parseFloat(self.discount());
		    if (discount > 0) {
		    	total = total * ((100 - discount)/100);
		    }

		    return total > 0 ? formatMoney(total) : '';
    	});

    	self.onDragged = function(item) {
    		refreshPDF();
    	}
	}

	function ItemModel() {
		var self = this;
		this.product_key = ko.observable('');
		this.notes = ko.observable('');
		this.cost = ko.observable();
		this.qty = ko.observable();
		this.tax = ko.observable();
		this.actionsVisible = ko.observable(false);

		this.rawTotal = ko.computed(function() {
			var cost = parseFloat(self.cost());
			var qty = parseFloat(self.qty());
			var tax = parseFloat(self.tax());
        	var value = cost * qty;
        	if (self.tax() > 0) {
        		//value = value * ((100 - this.tax())/100);
        	}
        	return value ? value : '';
    	});

		this.total = ko.computed(function() {
			var total = self.rawTotal();
			return total ? formatMoney(total) : '';
    	});

    	this.hideActions = function() {
			this.actionsVisible(false);
    	}

    	this.showActions = function() {
			this.actionsVisible(true);
    	}

    	this.isEmpty = function() {
    		return !self.product_key() && !self.notes() && !self.cost() && !self.qty() && !self.tax();
    	}
	}

	function checkWordWrap(event)
	{
		var doc = new jsPDF('p', 'pt');
		doc.setFont('Helvetica','');
		doc.setFontSize(10);

		var $textarea = $(event.target || event.srcElement);
	    var lines = $textarea.val().split("\n");
	    for (var i = 0; i < lines.length; i++) {
	    	var numLines = doc.splitTextToSize(lines[i], 200).length;
	        if (numLines <= 1) continue;
	        var j = 0; space = lines[i].length;
	        while (j++ < lines[i].length) {
	            if (lines[i].charAt(j) === " ") space = j;
	        }
	        lines[i + 1] = lines[i].substring(space + 1) + (lines[i + 1] || "");
	        lines[i] = lines[i].substring(0, space);
	    }
	    
	    var val = lines.slice(0, 6).join("\n");
	    if (val != $textarea.val())
	    {
			var model = ko.dataFor($textarea[0]);
			model.notes(val);
			refreshPDF();
	    }
	    $textarea.height(val.split('\n').length * 22);
	    onChange();
	}

	function onChange()
	{
		var hasEmpty = false;
		for(var i=0; i<model.items().length; i++) {
			var item = model.items()[i];
			if (item.isEmpty()) {
				hasEmpty = true;
			}
		}
		if (!hasEmpty) {
			model.addItem();
		}
	}

	function toggleRecurring()
	{
		var enabled = $('#recurring').is(':checked');
		
		if (enabled) {
			$('#recurring_on').show();
			$('#recurring_off').hide();
			$('#email_button').prop('disabled', true);
		} else {
			$('#recurring_on').hide();
			$('#recurring_off').show();			
			$('#email_button').prop('disabled', false);
		}		

		/*
		$('#col_1').toggleClass('col-md-6 col-md-5');
		$('#col_2').toggleClass('col-md-5 col-md-3');
		
		if (show) {
			setTimeout(function() {
				$('#col_3').show();
			}, 500);		
		} else {
			$('#col_3').hide();
		}

		$('#showRecurring,#hideRecurring').toggle();

		if (!show) {
			$('#how_often, #start_date, #end_date').val('')
		}
		*/
	};	

	function updateRecurringStats()
	{
		/*
		var howOften = $('#how_often').val();
		var startDate = $('#start_date').val();
		var endDate = $('#end_date').val();
		console.log("%s %s %s", howOften, startDate, endDate);

		var str = "Send ";
		if (!endDate) {
			str += " an unlimited number of ";
		} else {
			str += "";
		}
		str += " emails";
		$('#stats').html(str);
		*/
	}

	var products = {{ $products }};
	var clients = {{ $clients }};	
	var clientMap = {};

	for (var i=0; i<clients.length; i++) {
		var client = clients[i];
		clientMap[client.public_id] = client;
	}

	window.model = new InvoiceModel();
	ko.applyBindings(model);


	</script>


@stop