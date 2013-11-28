@extends('header')


@section('onReady')
	$('input#name').focus();
@stop


@section('content')

	<!--<h3>{{ $title }} Client</h3>-->
	
	{{ Former::open($url)->addClass('col-md-9 col-md-offset-1 main_form')->method($method)->rules(array(
  		'name' => 'required',
  		'email' => 'email'  		
	)); }}

	@if ($client)
		{{ Former::populate($client) }}
	@endif

	
	{{ Former::legend('Organization') }}
	{{ Former::text('name') }}
	{{ Former::text('work_phone')->label('Phone') }}
	{{ Former::textarea('notes') }}

	{{ Former::legend('Contacts') }}
	<div data-bind="foreach: contacts">
		{{ Former::hidden('id')->data_bind("value: id, valueUpdate: 'afterkeydown'") }}
		{{ Former::text('first_name')->data_bind("value: first_name, valueUpdate: 'afterkeydown'") }}
		{{ Former::text('last_name')->data_bind("value: last_name, valueUpdate: 'afterkeydown'") }}
		{{ Former::text('email')->data_bind("value: email, valueUpdate: 'afterkeydown'") }}
		{{ Former::text('phone')->data_bind("value: phone, valueUpdate: 'afterkeydown'") }}	

		<div class="form-group">
			<div class="col-lg-8 col-lg-offset-4">
				<span data-bind="visible: $index() === ($parent.contacts().length - 1)">
					{{ link_to('#', 'Add contact', array('onclick'=>'return addContact()')) }}
				</span>
				<span data-bind="visible: $parent.contacts().length > 1" class="pull-right">
					{{ link_to('#', 'Remove contact', array('data-bind'=>'click: $parent.removeContact')) }}
				</span>					
			</div>
		</div>
		<div class="clearfix"></div>

	</div>
	
	{{ Former::legend('Address') }}
	{{ Former::text('address1') }}
	{{ Former::text('address2') }}
	{{ Former::text('city') }}
	{{ Former::text('state') }}
	{{ Former::text('postal_code') }}

	{{ Former::hidden('data')->data_bind("value: ko.toJSON(model)") }}	

	{{ Former::actions( Button::lg_primary_submit('Save') ) }}
	{{ Former::close() }}

	<script type="text/javascript">

	function ContactModel() {
		var self = this;
		self.id = ko.observable('');
		self.first_name = ko.observable('');
		self.last_name = ko.observable('');
		self.email = ko.observable('');
		self.phone = ko.observable('');
	}

	function ContactsModel() {
		var self = this;
		self.contacts = ko.observableArray();
	}

	@if ($client)
		window.model = ko.mapping.fromJS({{ $client }});			
	@else
		window.model = new ContactsModel();
		addContact();
	@endif
	ko.applyBindings(model);

	function addContact() {
		model.contacts.push(new ContactModel());
		return false;
	}

	model.removeContact = function() {
		model.contacts.remove(this);
	}
	
	</script>

@stop