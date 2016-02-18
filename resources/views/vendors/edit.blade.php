@extends('header')


@section('onReady')
	$('input#name').focus();
@stop

@section('content')

@if ($errors->first('vendorcontacts'))
    <div class="alert alert-danger">{{ trans($errors->first('vendorcontacts')) }}</div>
@endif

<div class="row">

	{!! Former::open($url)
            ->autocomplete('off')
            ->rules([
                'namey' => 'required',
                'email' => 'email'
            ])->addClass('col-md-12 warn-on-exit')
            ->method($method) !!}

    @include('partials.autocomplete_fix')

	@if ($vendor)
		{!! Former::populate($vendor) !!}
        {!! Former::hidden('public_id') !!}
	@endif

	<div class="row">
		<div class="col-md-6">


        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.organization') !!}</h3>
          </div>
            <div class="panel-body">

			{!! Former::text('name')->data_bind("attr { placeholder: placeholderName }") !!}
			{!! Former::text('id_number') !!}
                        {!! Former::text('vat_number') !!}
                        {!! Former::text('website') !!}
			{!! Former::text('work_phone') !!}

            </div>
            </div>

        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.address') !!}</h3>
          </div>
            <div class="panel-body">

			{!! Former::text('address1') !!}
			{!! Former::text('address2') !!}
			{!! Former::text('city') !!}
			{!! Former::text('state') !!}
			{!! Former::text('postal_code') !!}
			{!! Former::select('country_id')->addOption('','')
				->fromQuery($countries, 'name', 'id') !!}

        </div>
        </div>
		</div>
		<div class="col-md-6">


        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.contacts') !!}</h3>
          </div>
            <div class="panel-body">

			<div data-bind='template: { foreach: vendorcontacts,
		                            beforeRemove: hideContact,
		                            afterAdd: showContact }'>
				{!! Former::hidden('public_id')->data_bind("value: public_id, valueUpdate: 'afterkeydown',
                        attr: {name: 'vendorcontacts[' + \$index() + '][public_id]'}") !!}
				{!! Former::text('first_name')->data_bind("value: first_name, valueUpdate: 'afterkeydown',
                        attr: {name: 'vendorcontacts[' + \$index() + '][first_name]'}") !!}
				{!! Former::text('last_name')->data_bind("value: last_name, valueUpdate: 'afterkeydown',
                        attr: {name: 'vendorcontacts[' + \$index() + '][last_name]'}") !!}
				{!! Former::text('email')->data_bind("value: email, valueUpdate: 'afterkeydown',
                        attr: {name: 'vendorcontacts[' + \$index() + '][email]', id:'email'+\$index()}") !!}
				{!! Former::text('phone')->data_bind("value: phone, valueUpdate: 'afterkeydown',
                        attr: {name: 'vendorcontacts[' + \$index() + '][phone]'}") !!}

				<div class="form-group">
					<div class="col-lg-8 col-lg-offset-4 bold">
						<span class="redlink bold" data-bind="visible: $parent.vendorcontacts().length > 1">
							{!! link_to('#', trans('texts.remove_contact').' -', array('data-bind'=>'click: $parent.removeContact')) !!}
						</span>
						<span data-bind="visible: $index() === ($parent.vendorcontacts().length - 1)" class="pull-right greenlink bold">
							{!! link_to('#', trans('texts.add_contact').' +', array('onclick'=>'return addContact()')) !!}
						</span>
					</div>
				</div>
			</div>
            </div>
            </div>


        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.additional_info') !!}</h3>
          </div>
            <div class="panel-body">

            {!! Former::select('currency_id')->addOption('','')
                ->placeholder($account->currency ? $account->currency->name : '')
                ->fromQuery($currencies, 'name', 'id') !!}
			{!! Former::textarea('private_notes')->rows(6) !!}


            @if (isset($proPlanPaid))
                {!! Former::populateField('pro_plan_paid', $proPlanPaid) !!}
                {!! Former::text('pro_plan_paid')
                            ->data_date_format('yyyy-mm-dd')
                            ->addGroupClass('pro_plan_paid_date')
                            ->append('<i class="glyphicon glyphicon-calendar"></i>') !!}
                <script type="text/javascript">
                    $(function() {
                        $('#pro_plan_paid').datepicker();
                    });
                </script>
            @endif

            </div>
            </div>

		</div>
	</div>


	{!! Former::hidden('data')->data_bind("value: ko.toJSON(model)") !!}

	<script type="text/javascript">

	$(function() {
		$('#country_id').combobox();
	});

	function VendorContactModel(data) {
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

	function VendorModel(data) {
		var self = this;

        self.vendorcontacts = ko.observableArray();

		self.mapping = {
		    'vendorcontacts': {
		    	create: function(options) {
		    		return new VendorContactModel(options.data);
		    	}
		    }
		}

		if (data) {
			ko.mapping.fromJS(data, self.mapping, this);
		} else {
			self.vendorcontacts.push(new VendorContactModel());
		}

		self.placeholderName = ko.computed(function() {
			if (self.vendorcontacts().length == 0) return '';
			var contact = self.vendorcontacts()[0];
			if (contact.first_name() || contact.last_name()) {
				return contact.first_name() + ' ' + contact.last_name();
			} else {
				return contact.email();
			}
		});
	}

    @if ($data)
        window.model = new VendorModel({!! $data !!});
    @else
	    window.model = new VendorModel({!! $vendor !!});
    @endif

	model.showContact = function(elem) { if (elem.nodeType === 1) $(elem).hide().slideDown() }
	model.hideContact = function(elem) { if (elem.nodeType === 1) $(elem).slideUp(function() { $(elem).remove(); }) }


	ko.applyBindings(model);

	function addContact() {
		model.vendorcontacts.push(new VendorContactModel());
		return false;
	}

	model.removeContact = function() {
		model.vendorcontacts.remove(this);
	}


	</script>

	<center class="buttons">
    	{!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/vendors/' . ($vendor ? $vendor->public_id : '')))->appendIcon(Icon::create('remove-circle')) !!}
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
	</center>

	{!! Former::close() !!}
</div>
@stop
