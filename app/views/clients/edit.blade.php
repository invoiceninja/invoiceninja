@extends('header')


@section('onReady')
	$('input#name').focus();
@stop

@section('content')

	<!--<h3>{{ $title }} Client</h3>-->
	
	{{ Former::open($url)->addClass('col-md-10 col-md-offset-1 main_form')->method($method)->rules(array(
  		'email' => 'email|required'  		
	)); }}

	@if ($client)
		{{ Former::populate($client) }}
	@endif


	<div class="row">
		<div class="col-md-6">


			{{ Former::legend('Organization') }}
			{{ Former::text('name')->data_bind("attr { placeholder: placeholderName }") }}
			{{ Former::text('website') }}
			{{ Former::text('work_phone')->label('Phone') }}
			
			
			{{ Former::legend('Address') }}
			{{ Former::text('address1')->label('Street') }}
			{{ Former::text('address2')->label('Apt/Suite') }}
			{{ Former::text('city') }}
			{{ Former::text('state')->label('State/Province') }}
			{{ Former::text('postal_code') }}
			{{ Former::select('country_id')->addOption('','')->label('Country')
				->fromQuery($countries, 'name', 'id') }}


		</div>
		<div class="col-md-6">

			{{ Former::legend('Contacts') }}
			<div data-bind='template: { foreach: contacts,
		                            beforeRemove: hideContact,
		                            afterAdd: showContact }'>
				{{ Former::hidden('public_id')->data_bind("value: public_id, valueUpdate: 'afterkeydown'") }}
				{{ Former::text('first_name')->data_bind("value: first_name, valueUpdate: 'afterkeydown'") }}
				{{ Former::text('last_name')->data_bind("value: last_name, valueUpdate: 'afterkeydown'") }}
				{{ Former::text('email')->data_bind("value: email, valueUpdate: 'afterkeydown'") }}
				{{ Former::text('phone')->data_bind("value: phone, valueUpdate: 'afterkeydown'") }}	

				<div class="form-group">
					<div class="col-lg-8 col-lg-offset-4">
						<span data-bind="visible: $parent.contacts().length > 1">
							{{ link_to('#', 'Remove contact', array('data-bind'=>'click: $parent.removeContact')) }}
						</span>					
						<span data-bind="visible: $index() === ($parent.contacts().length - 1)" class="pull-right">
							{{ link_to('#', 'Add contact', array('onclick'=>'return addContact()')) }}
						</span>
					</div>
				</div>
			</div>

			{{ Former::legend('Additional Info') }}
			{{ Former::select('payment_terms')->addOption('','')
				->fromQuery($paymentTerms, 'name', 'num_days') }}
			{{ Former::select('currency_id')->addOption('','')->label('Currency')
				->fromQuery($currencies, 'name', 'id') }}
			{{ Former::select('size_id')->addOption('','')->label('Size')
				->fromQuery($sizes, 'name', 'id') }}
			{{ Former::select('industry_id')->addOption('','')->label('Industry')
				->fromQuery($industries, 'name', 'id') }}
			{{ Former::textarea('private_notes') }}


		</div>
	</div>


	{{ Former::hidden('data')->data_bind("value: ko.toJSON(model)") }}	

	<script type="text/javascript">

	$(function() {
		$('#country_id').combobox();
	});

	function ContactModel(data) {
		var self = this;
		self.public_id = ko.observable('');
		self.first_name = ko.observable('');
		self.last_name = ko.observable('');
		self.email = ko.observable('');
		self.phone = ko.observable('');

		if (data) {
			ko.mapping.fromJS(data, {}, this);			
		}		
	}

	function ContactsModel(data) {
		var self = this;
		self.contacts = ko.observableArray();

		self.mapping = {
		    'contacts': {
		    	create: function(options) {
		    		return new ContactModel(options.data);
		    	}
		    }
		}		

		if (data) {
			ko.mapping.fromJS(data, self.mapping, this);			
		} else {
			self.contacts.push(new ContactModel());
		}

		self.placeholderName = ko.computed(function() {
			if (self.contacts().length == 0) return '';
			var contact = self.contacts()[0];
			if (contact.first_name() || contact.last_name()) {
				return contact.first_name() + ' ' + contact.last_name();
			} else {
				return contact.email();
			}
		});	
	}

	window.model = new ContactsModel({{ $client }});

	model.showContact = function(elem) { if (elem.nodeType === 1) $(elem).hide().slideDown() }
	model.hideContact = function(elem) { if (elem.nodeType === 1) $(elem).slideUp(function() { $(elem).remove(); }) }


	ko.applyBindings(model);

	function addContact() {
		model.contacts.push(new ContactModel());
		return false;
	}

	model.removeContact = function() {
		model.contacts.remove(this);
	}


	</script>



	<center style="margin-top:16px">
		{{ Button::lg_primary_submit('Save') }} &nbsp;|&nbsp;
		{{ link_to('clients/' . ($client ? $client->public_id : ''), 'Cancel') }}	
	</center>

	{{ Former::close() }}

@stop