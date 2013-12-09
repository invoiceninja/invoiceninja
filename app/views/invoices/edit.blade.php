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
		'invoice_number' => 'required',
  		'invoice_date' => 'required',
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
	@endif
    
    <div class="row" style="min-height:195px">
    	<div class="col-md-6" id="col_1">
			{{ Former::select('client')->addOption('', '')->fromQuery($clients, 'name', 'public_id')->select($client ? $client->public_id : '')->addGroupClass('client_select')
				->help('<a style="cursor:pointer" data-toggle="modal" id="modalLink" onclick="showCreateNew()">Create new client</a>') }}
			{{ Former::textarea('notes') }}			
		</div>
		<div class="col-md-5" id="col_2">
			{{ Former::text('invoice_number')->label('Invoice #') }}
			{{-- Former::text('invoice_date')->label('Invoice Date')->data_date_format('yyyy-mm-dd') --}}
			{{ Former::text('invoice_date') }}
			{{ Former::text('due_date')->help('<a id="showRecurring" style="cursor:pointer" onclick="toggleRecurring(true)">Enable recurring</a>') }}
			{{-- Former::text('discount')->data_bind("value: discount, valueUpdate: 'afterkeydown'") --}}

			
		</div>
		<div class="col-md-3" id="col_3" style="display:none">
			{{ Former::select('how_often')->options($frequencies) }}
			{{ Former::text('start_date') }}
			{{ Former::text('end_date')->help('<a id="hideRecurring" style="cursor:pointer;display:none" onclick="toggleRecurring(false)">Disable recurring</a>') }}
			
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
	        	<th>Unit&nbsp;Cost</th>
	        	<th>Quantity</th>
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
				<td colspan="2"><b>Invoice Total</b></td>
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

		@if ($invoice)		
			{{ DropdownButton::normal('Download PDF',
				  Navigation::links(
				    array(
				    	array('Download PDF', "javascript:onDownloadClick()"),
				     	array(Navigation::DIVIDER),
				     	array('Archive Invoice', "javascript:onArchiveClick()"),
				     	array('Delete Invoice', "javascript:onDeleteClick()"),
				    )
				  )
				, array('id'=>'actionDropDown','style'=>'text-align:left'))->split(); }}				
		@else
			{{ Button::normal('Download PDF', array('onclick' => 'onDownloadClick()')) }}		
		@endif

		{{ Button::primary_submit('Save Invoice') }}		
		{{ Button::primary('Send Email', array('onclick' => 'onEmailClick()')) }}		
	</div>
	<p>&nbsp;</p>
	
	<!-- <textarea rows="20" cols="120" id="pdfText" onkeyup="runCode()"></textarea> -->
	<!-- <iframe frameborder="1" width="100%" height="600" style="display:block;margin: 0 auto"></iframe>	-->
	<!-- <iframe frameborder="1" width="100%" height="500"></iframe> -->
	<canvas id="the-canvas" style="width:100%;border:solid 1px #CCCCCC;"></canvas>


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

		$('#country_id').combobox();

		$('#invoice_date').datepicker({
			autoclose: true,
			todayHighlight: true
		}).on('changeDate', function(e) {
			refreshPDF();
		});

		$('#due_date,#start_date').datepicker({
			autoclose: true,
			todayHighlight: true
		});

		var $input = $('select#client');
		$input.combobox();
		$('.client_select input.form-control').on('change', function(e) {
			refreshPDF();			
			if ($('input[name=client]').val() != '-1') {
				$('#modalLink').text('Create new client');	
			}
		});
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
			onDownloadClick();
		});


		@if ($invoice && $invoice->isRecurring())
			toggleRecurring(true);
		@endif;

		applyComboboxListeners();
		refreshPDF();		
	});

	function showCreateNew() {
		if ($('input[name=client]').val() != '-1') {
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
		var string = doc.output('dataurlstring');
			
		//console.log(string);
		var pdfAsArray = convertDataURIToBinary(string);	
	    PDFJS.getDocument(pdfAsArray).then(function getPdfHelloWorld(pdf) {

	      pdf.getPage(1).then(function getPageHelloWorld(page) {
	        var scale = 1.5;
	        var viewport = page.getViewport(scale);

	        var canvas = document.getElementById('the-canvas');
	        var context = canvas.getContext('2d');
	        canvas.height = viewport.height;
	        canvas.width = viewport.width;

	        page.render({canvasContext: context, viewport: viewport});
	      });
	    });				

		//$('iframe').attr('src', string);		
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
			$('input[name=client]').val('-1');
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

	function toggleRecurring(show)
	{
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