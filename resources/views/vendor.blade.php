{!!--  // vendor --!!}
<div class="row">
	<div class="col-md-6">

		{!! Former::legend('Organization') !!}
		{!! Former::text('name') !!}
        {!! Former::text('id_number') !!}
        {!! Former::text('vat_number') !!}
		{!! Former::text('work_phone')->label('Phone') !!}
		{!! Former::textarea('notes') !!}

		
		{!! Former::legend('Address') !!}
		{!! Former::text('address1')->label('Street') !!}
		{!! Former::text('address2')->label('Apt/Floor') !!}
		{!! Former::text('city') !!}
		{!! Former::text('state') !!}
		{!! Former::text('postal_code') !!}
		{!! Former::select('country_id')->addOption('','')->label('Country')
			->fromQuery($countries, 'name', 'id') !!}


	</div>
	<div class="col-md-6">

		{!! Former::legend('VendorContacts') !!}
		<div data-bind='template: { foreach: vendor_contacts,
	                            beforeRemove: hideContact,
	                            afterAdd: showContact }'>
			{!! Former::hidden('public_id')->data_bind("value: public_id, valueUpdate: 'afterkeydown'") !!}
			{!! Former::text('first_name')->data_bind("value: first_name, valueUpdate: 'afterkeydown'") !!}
			{!! Former::text('last_name')->data_bind("value: last_name, valueUpdate: 'afterkeydown'") !!}
			{!! Former::text('email')->data_bind("value: email, valueUpdate: 'afterkeydown'") !!}
			{!! Former::text('phone')->data_bind("value: phone, valueUpdate: 'afterkeydown'") !!}

			<div class="form-group">
				<div class="col-lg-8 col-lg-offset-4">
					<span data-bind="visible: $parent.vendor_contacts().length > 1">
						{!! link_to('#', 'Remove contact', array('data-bind'=>'click: $parent.removeContact')) !!}
					</span>					
					<span data-bind="visible: $index() === ($parent.vendor_contacts().length - 1)" class="pull-right">
						{!! link_to('#', 'Add contact', array('onclick'=>'return addContact()')) !!}
					</span>
				</div>
			</div>

		</div>

	</div>
</div>


{!! Former::hidden('data')->data_bind("value: ko.toJSON(model)") !!}

<script type="text/javascript">

$(function() {
	$('#country_id').combobox();
});

function VendorContactModel() {
	var self = this;
	self.public_id = ko.observable('');
	self.first_name = ko.observable('');
	self.last_name = ko.observable('');
	self.email = ko.observable('');
	self.phone = ko.observable('');
}

function VendorContactsModel() {
	var self = this;
	self.vendor_contacts = ko.observableArray();
}

@if ($vendor)
	window.model = ko.mapping.fromJS({!! $vendor !!});			
@else
	window.model = new VendorContactsModel();
	addContact();
@endif

model.showContact = function(elem) { if (elem.nodeType === 1) $(elem).hide().slideDown() }
model.hideContact = function(elem) { if (elem.nodeType === 1) $(elem).slideUp(function() { $(elem).remove(); }) }


ko.applyBindings(model);

function addContact() {
	model.vendor_contacts.push(new VendorContactModel());
	return false;
}

model.removeContact = function() {
	model.vendor_contacts.remove(this);
}


</script>

