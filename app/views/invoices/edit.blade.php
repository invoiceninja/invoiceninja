@extends('header')

@section('content')

	</div>
	<div>
	<div class=".container">
	<p>&nbsp;</p>

	<div class="row">

		<div class="col-md-6 col-sm-10">
			<div>
				{{ Former::open($url)->method($method)->addClass('main_form')->rules(array(
			  		'number' => 'required',
				)); }}


				<!-- <h3>{{ $title }} Invoice</h3> -->

				@if ($invoice)
					{{ Former::populate($invoice); }}
					{{ Former::populateField('issued_on', DateTime::createFromFormat('Y-m-d', $invoice->issued_on)->format('m/d/Y')); }}
				@else
					{{ Former::populateField('issued_on', date('m/d/Y')) }}
				@endif
			    
			    <div class="row">
			    	<div class="col-md-6">
						{{ Former::select('client')->addOption('', '')->fromQuery($clients, 'name', 'id')->select($client ? $client->id : '')
							->help('<a class="ul" data-toggle="modal" data-target="#myModal">Create new client</a>'); }}
					</div>
					<div class="col-md-6">
						{{ Former::text('number') }}
						{{ Former::text('issued_on') }}
						{{ Former::text('discount')->data_bind("value: discount, valueUpdate: 'afterkeydown'") }}
					</div>
				</div>

				<p>&nbsp;</p>
			</div>
			<div style="padding-left:16px">
				<input type="text" name="items" data-bind="value: ko.toJSON(items)" style="display:none"/>
				<table class="table invoice-table" style="margin-bottom: 0px !important;">
				    <thead>
				        <tr>
				        	<th class="hide-border"></th>
				        	<th>Item</th>
				        	<th>Description</th>
				        	<th>Unit&nbsp;Cost</th>
				        	<th>Quantity</th>
				        	<th>Line&nbsp;Total</th>
				        	<th class="hide-border"></th>
				        </tr>
				    </thead>
				    <tbody data-bind="sortable: { data: items, afterMove: onDragged }">
				    	<tr data-bind="event: { mouseover: showActions, mouseout: hideActions }" class="sortable-row">
				        	<td style="width:50px;" class="hide-border">
				        		<i data-bind="click: $parent.addItem, visible: actionsVisible" class="fa fa-plus-circle" style="cursor:pointer" title="Add item"></i>&nbsp;
				        		<i data-bind="visible: actionsVisible" class="fa fa-sort"></i>
				        	</td>
				            <td style="width:120px">
				            	<input data-bind="value: product_key, valueUpdate: 'afterkeydown'" onchange="refreshPDF()"/>
				            </td>
				            <td style="width:300px">
				            	<textarea data-bind="value: notes, valueUpdate: 'afterkeydown'" rows="1" cols="60" onchange="refreshPDF()"></textarea>
				            </td>
				            <td style="width:100px">
				            	<input data-bind="value: cost, valueUpdate: 'afterkeydown'" style="text-align: right" onchange="refreshPDF()"//>
				            </td>
				            <td style="width:80px">
				            	<input data-bind="value: qty, valueUpdate: 'afterkeydown'" style="text-align: right" onchange="refreshPDF()"//>
				            </td>
				            <!--
				            <td style="width:100px">
				            	<input data-bind="value: tax, valueUpdate: 'afterkeydown'"/>
				            </td>
				        	-->
				            <td style="width:100px;background-color: #FFFFFF;text-align: right">
				            	<span data-bind="text: total"></span>
				            </td>
				        	<td style="width:20px; cursor:pointer" class="hide-border">
				        		&nbsp;<i data-bind="click: $parent.removeItem, visible: actionsVisible() &amp;&amp; $parent.items().length > 1" class="fa fa-minus-circle" title="Remove item"/>
				        	</td>
				        </tr>
					</tbody>
					<tfoot>	        
				        <tr data-bind="visible: subtotal() != total()">
				        	<td colspan="3" class="hide-border"/>
							<td colspan="2">Subtotal</td>
							<td style="text-align: right"><span data-bind="text: subtotal"/></td>
				        </tr>
				        <tr data-bind="visible: discount() > 0">
				        	<td colspan="3" class="hide-border"/>
							<td colspan="2">Discount</td>
							<td style="text-align: right"><span data-bind="text: discounted"/></td>
				        </tr>
				        <tr>
				        	<td colspan="3" class="hide-border"/>
							<td colspan="2">Invoice Total</td>
							<td style="text-align: right"><span data-bind="text: total"/></td>
				        </tr>
				    </tfoot>
				</table>
			</div>

			<input type="checkbox" name="send_email_checkBox" id="send_email_checkBox" value="true" style="display:none"/>

			<p>&nbsp;</p>
			<div class="form-actions">
				{{ Button::primary('Download PDF', array('onclick' => 'onDownloadClick()')) }}
				{{ Button::primary_submit('Save Invoice') }}
				{{ Button::primary('Send Email', array('onclick' => 'onEmailClick()')) }}		
			</div>
			<p>&nbsp;</p>
			
		</div>
		<div class="col-md-6">	
			<!-- <textarea rows="20" cols="120" id="pdfText" onkeyup="runCode()"></textarea> -->
			<!-- <iframe frameborder="1" width="600" height="600" style="display:block;margin: 0 auto"></iframe> -->
			<iframe frameborder="1" width="92%" height="600" style="display:block;margin: 0 auto"></iframe>	
		</div>

	</div>


	<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	  <div class="modal-dialog">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	        <h4 class="modal-title" id="myModalLabel">New Client</h4>
	      </div>
	      <div class="modal-body" style="min-height:80px">
	      	<div class="form-group required">
	      		<label for="name" class="control-label col-lg-2 col-sm-4">Name<sup>*</sup></label>
	      		<div class="col-lg-10 col-sm-8">
	      			<input class="form-control" id="client_name" type="text" name="client_name" onkeypress="nameKeyPress(event)">
	      			<span class="help-block" id="nameHelp" style="display: none">Please provide a value</span><span>&nbsp;</span>
	      		</div>	      		
	      	</div>
	      	<div class="form-group required">
	      		<label for="email" class="control-label col-lg-2 col-sm-4">Email<sup>*</sup></label>
	      		<div class="col-lg-10 col-sm-8">
	      			<input class="form-control" id="client_email" type="text" name="client_email" onkeypress="nameKeyPress(event)">
	      			<span class="help-block" id="emailHelp" style="display: none">Please provide a value</span><span>&nbsp;</span>
	      		</div>	      		
	      	</div>
	      </div>
	      <div class="modal-footer">
	      	<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
	        <button type="button" class="btn btn-primary" onclick="newClient()">Save</button>	      	
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

		$('#issued_on').datepicker({
			autoclose: true,
			todayHighlight: true
		}).on('changeDate', function(e) {
			refreshPDF();
		});

		var $input = $('select#client');
		$input.combobox();
		$('.combobox-container input.form-control').attr('name', 'client_combobox').on('change', function(e) {
			refreshPDF();
		});

		@if ($client)
			$('input#number').focus();
		@else
			$('[name="client_combobox"]').focus();
		@endif
		
		/*
		$('#myModal').on('hidden.bs.modal', function () {
			$('#popup_client_name').val('');
		})
		*/

		$('#myModal').on('shown.bs.modal', function () {
			$('#client_name').focus();
		})

		$('#number').change(refreshPDF);

		refreshPDF();
		
	});

	function runCode() {
		var text = $('#pdfText').val();
		eval(text);
	}

	function createInvoiceModel() {
		var invoice = {
			number: $('#number').val(),
			issued_on: $('#issued_on').val(),
			client: {
				name: $('[name="client_combobox"]').val()
			},
			@if (file_exists($account->getLogoPath()))
				image: "{{ HTML::image_data($account->getLogoPath()) }}",			
				imageWidth: {{ $account->getLogoWidth() }},
				imageHeight: {{ $account->getLogoHeight() }},
			@endif
			invoice_items: []
		};

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
		var invoice = createInvoiceModel();
		var doc = generatePDF(invoice);
		var string = doc.output('datauristring');
		$('iframe').attr('src', string);
	}	

	function onDownloadClick() {
		var invoice = createInvoiceModel();
		var doc = generatePDF(invoice);
		doc.save('Invoice-' + $('#number').val() + '.pdf');
	}

	function onEmailClick() {
		if (confirm('Are you sure you want to email this invoice?')) {
			$('#send_email_checkBox').prop("checked",true);
			$('.main_form').submit();
		}
	}

	function newClient() {
		var name = $('#client_name').val();
		var email = $('#client_email').val();
		if (!name || !email) {
			if (!name) $('#nameHelp').css( "display", "block" );
			if (!email) $('#emailHelp').css( "display", "block" );
		} else {
			$('.combobox-container input[name=client]').val('-1');
			$('.combobox-container input.form-control').val(name);
			$('.combobox-container').addClass('combobox-selected');

			$('#nameHelp').css( "display", "none" );
			$('#emailHelp').css( "display", "none" );
			//$('#client_name').val('');
			$('#myModal').modal('hide');
			$('input#number').focus();

			refreshPDF();
		}
	}		

	function nameKeyPress(event) {		
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

		@if ($invoice)
			self.discount({{ $invoice->discount }});
			@if (count($invoice->invoice_items) > 0)
				@foreach ($invoice->invoice_items as $item)
					var item = new ItemModel();
					item.product_key("{{ $item->product_key }}");
					item.notes('{{ str_replace("\n", "\\notes", $item->notes) }}');
					item.cost("{{ $item->cost }}");
					item.qty("{{ $item->qty }}");				
					self.items.push(item);				
				@endforeach
			@else
				self.items.push(new ItemModel());
			@endif
		@else
			var model1 = new ItemModel();
			var model2 = new ItemModel();
			/*
			model1.item('TEST');
			model1.notes('Some test text');
			model1.cost(10);
			model1.qty(1);
			*/
			self.items.push(model1);	
			self.items.push(model2);	
		@endif		
		
		self.removeItem = function(item) {
			self.items.remove(item);
			refreshPDF();
		}

		self.addItem = function() {
			self.items.push(new ItemModel());	
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

		    var discount = parseInt(self.discount());
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
			var qty = parseInt(self.qty());
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
	}

	window.model = new InvoiceModel();
	ko.applyBindings(model);

	</script>


@stop