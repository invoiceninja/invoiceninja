@extends('header')

@section('onReady')
	$('input#name').focus();
@stop

@section('head')
	@if (config('ninja.google_maps_api_key'))
		@include('partials.google_geocode')
	@endif
@stop

@section('content')

@if ($errors->first('contacts'))
    <div class="alert alert-danger">{{ trans($errors->first('contacts')) }}</div>
@endif

<div class="row">

	{!! Former::open($url)
            ->autocomplete('off')
            ->rules(
                ['email' => 'email']
            )->addClass('col-md-12 warn-on-exit')
            ->method($method) !!}

    @include('partials.autocomplete_fix')

	@if ($client)
		{!! Former::populate($client) !!}
		{!! Former::populateField('task_rate', floatval($client->task_rate) ? Utils::roundSignificant($client->task_rate) : '') !!}
		{!! Former::populateField('show_tasks_in_portal', intval($client->show_tasks_in_portal)) !!}
		{!! Former::populateField('send_reminders', intval($client->send_reminders)) !!}
        {!! Former::hidden('public_id') !!}
	@else
		{!! Former::populateField('invoice_number_counter', 1) !!}
		{!! Former::populateField('quote_number_counter', 1) !!}
		{!! Former::populateField('send_reminders', 1) !!}
		@if ($account->client_number_counter)
			{!! Former::populateField('id_number', $account->getNextNumber()) !!}
		@endif
	@endif

	<div class="row">
		<div class="col-md-6">


        <div class="panel panel-default" style="min-height: 380px">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.details') !!}</h3>
          </div>
            <div class="panel-body">

			{!! Former::text('name')->data_bind("attr { placeholder: placeholderName }") !!}
			{!! Former::text('id_number')->placeholder($account->clientNumbersEnabled() ? $account->getNextNumber() : ' ') !!}
            {!! Former::text('vat_number') !!}
            {!! Former::text('website') !!}
			{!! Former::text('work_phone') !!}


			@include('partials/custom_fields', ['entityType' => ENTITY_CLIENT])

			@if ($account->usesClientInvoiceCounter())
				{!! Former::text('invoice_number_counter')->label('invoice_counter') !!}

				@if (! $account->share_counter)
					{!! Former::text('quote_number_counter')->label('quote_counter') !!}
				@endif
			@endif
            </div>
        </div>

		<div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.address') !!}</h3>
          </div>
            <div class="panel-body">

				<div role="tabpanel">
					<ul class="nav nav-tabs" role="tablist" style="border: none">
						<li role="presentation" class="active">
							<a href="#billing_address" aria-controls="billing_address" role="tab" data-toggle="tab">{{ trans('texts.billing_address') }}</a>
						</li>
						<li role="presentation">
							<a href="#shipping_address" aria-controls="shipping_address" role="tab" data-toggle="tab">{{ trans('texts.shipping_address') }}</a>
						</li>
					</ul>
				</div>
				<div class="tab-content" style="padding-top:24px;">
					<div role="tabpanel" class="tab-pane active" id="billing_address">
						{!! Former::text('address1') !!}
						{!! Former::text('address2') !!}
						{!! Former::text('city') !!}
						{!! Former::text('state') !!}
						{!! Former::text('postal_code')
								->oninput(config('ninja.google_maps_api_key') ? 'lookupPostalCode()' : '') !!}
						{!! Former::select('country_id')->addOption('','')
							->autocomplete('off')
							->fromQuery($countries, 'name', 'id') !!}

						<div class="form-group" id="copyShippingDiv" style="display:none;">
							<label for="city" class="control-label col-lg-4 col-sm-4"></label>
							<div class="col-lg-8 col-sm-8">
								{!! Button::normal(trans('texts.copy_shipping'))->small() !!}
							</div>
						</div>

					</div>
					<div role="tabpanel" class="tab-pane" id="shipping_address">
						{!! Former::text('shipping_address1')->label('address1') !!}
						{!! Former::text('shipping_address2')->label('address2') !!}
						{!! Former::text('shipping_city')->label('city') !!}
						{!! Former::text('shipping_state')->label('state') !!}
						{!! Former::text('shipping_postal_code')
								->oninput(config('ninja.google_maps_api_key') ? 'lookupPostalCode(true)' : '')
								->label('postal_code') !!}
						{!! Former::select('shipping_country_id')->addOption('','')
							->autocomplete('off')
							->fromQuery($countries, 'name', 'id')->label('country_id') !!}

						<div class="form-group" id="copyBillingDiv" style="display:none;">
							<label for="city" class="control-label col-lg-4 col-sm-4"></label>
							<div class="col-lg-8 col-sm-8">
								{!! Button::normal(trans('texts.copy_billing'))->small() !!}
							</div>
						</div>
					</div>
				</div>

        </div>
        </div>
		</div>
		<div class="col-md-6">


        <div class="panel panel-default" style="min-height: 380px">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.contacts') !!}</h3>
          </div>
            <div class="panel-body">

			<div data-bind='template: { foreach: contacts,
		                            beforeRemove: hideContact,
		                            afterAdd: showContact }'>
				{!! Former::hidden('public_id')->data_bind("value: public_id, valueUpdate: 'afterkeydown',
                        attr: {name: 'contacts[' + \$index() + '][public_id]'}") !!}
				{!! Former::text('first_name')->data_bind("value: first_name, valueUpdate: 'afterkeydown',
                        attr: {name: 'contacts[' + \$index() + '][first_name]'}") !!}
				{!! Former::text('last_name')->data_bind("value: last_name, valueUpdate: 'afterkeydown',
                        attr: {name: 'contacts[' + \$index() + '][last_name]'}") !!}
				{!! Former::text('email')->data_bind("value: email, valueUpdate: 'afterkeydown',
                        attr: {name: 'contacts[' + \$index() + '][email]', id:'email'+\$index()}") !!}
				{!! Former::text('phone')->data_bind("value: phone, valueUpdate: 'afterkeydown',
                        attr: {name: 'contacts[' + \$index() + '][phone]'}") !!}
				@if ($account->hasFeature(FEATURE_CLIENT_PORTAL_PASSWORD) && $account->enable_portal_password)
					{!! Former::password('password')->data_bind("value: password()?'-%unchanged%-':'', valueUpdate: 'afterkeydown',
						attr: {name: 'contacts[' + \$index() + '][password]'}")->autocomplete('new-password')->data_lpignore('true') !!}
			    @endif

				@if (Auth::user()->hasFeature(FEATURE_INVOICE_SETTINGS))
					@if ($account->customLabel('contact1'))
						@include('partials.custom_field', [
							'field' => 'custom_contact1',
							'label' => $account->customLabel('contact1'),
							'databind' => "value: custom_value1, valueUpdate: 'afterkeydown',
									attr: {name: 'contacts[' + \$index() + '][custom_value1]'}",
						])
					@endif
					@if ($account->customLabel('contact2'))
						@include('partials.custom_field', [
							'field' => 'custom_contact2',
							'label' => $account->customLabel('contact2'),
							'databind' => "value: custom_value2, valueUpdate: 'afterkeydown',
									attr: {name: 'contacts[' + \$index() + '][custom_value2]'}",
						])
					@endif
				@endif

				<div class="form-group">
					<div class="col-lg-8 col-lg-offset-4 bold">
						<span class="redlink bold" data-bind="visible: $parent.contacts().length > 1">
							{!! link_to('#', trans('texts.remove_contact').' -', array('data-bind'=>'click: $parent.removeContact')) !!}
						</span>
						<span data-bind="visible: $index() === ($parent.contacts().length - 1)" class="pull-right greenlink bold">
							{!! link_to('#', trans('texts.add_contact').' +', array('onclick'=>'return addContact()')) !!}
						</span>
					</div>
				</div>
			</div>
            </div>
            </div>


        <div class="panel panel-default" style="min-height:505px">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.additional_info') !!}</h3>
          </div>
            <div class="panel-body">

				<div role="tabpanel">
					<ul class="nav nav-tabs" role="tablist" style="border: none">
						<li role="presentation" class="active">
							<a href="#settings" aria-controls="settings" role="tab" data-toggle="tab">{{ trans('texts.settings') }}</a>
						</li>
						<li role="presentation">
							<a href="#notes" aria-controls="notes" role="tab" data-toggle="tab">{{ trans('texts.notes') }}</a>
						</li>
						@if (Utils::isPaidPro())
							<li role="presentation">
	                            <a href="#messages" aria-controls="messages" role="tab" data-toggle="tab">{{ trans('texts.messages') }}</a>
	                        </li>
						@endif
						<li role="presentation">
							<a href="#classify" aria-controls="classify" role="tab" data-toggle="tab">{{ trans('texts.classify') }}</a>
						</li>
					</ul>
				</div>
				<div class="tab-content" style="padding-top:24px;">
					<div role="tabpanel" class="tab-pane active" id="settings">
						{!! Former::select('currency_id')->addOption('','')
			                ->placeholder($account->currency ? $account->currency->getTranslatedName() : '')
			                ->fromQuery($currencies, 'name', 'id') !!}
			            {!! Former::select('language_id')->addOption('','')
			                ->placeholder($account->language ? trans('texts.lang_'.$account->language->name) : '')
			                ->fromQuery($languages, 'name', 'id') !!}
						{!! Former::select('payment_terms')->addOption('','')
							->fromQuery(\App\Models\PaymentTerm::getSelectOptions(), 'name', 'num_days')
							->placeholder($account->present()->paymentTerms)
			                ->help(trans('texts.payment_terms_help') . ' | ' . link_to('/settings/payment_terms', trans('texts.customize_options'))) !!}
						@if ($account->isModuleEnabled(ENTITY_TASK))
							{!! Former::text('task_rate')
									->placeholder($account->present()->taskRate)
									->help('task_rate_help') !!}
							{!! Former::checkbox('show_tasks_in_portal')
						        ->text(trans('texts.show_tasks_in_portal'))
								->label('client_portal')
						        ->value(1) !!}
						@endif
						@if ($account->hasReminders())
							{!! Former::checkbox('send_reminders')
								->text('send_client_reminders')
								->label('reminders')
								->value(1) !!}
						@endif
					</div>
					<div role="tabpanel" class="tab-pane" id="notes">
						{!! Former::textarea('public_notes')->rows(6) !!}
						{!! Former::textarea('private_notes')->rows(6) !!}
					</div>
					@if (Utils::isPaidPro())
						<div role="tabpanel" class="tab-pane" id="messages">
							@foreach (App\Models\Account::$customMessageTypes as $type)
								{!! Former::textarea('custom_messages[' . $type . ']')
										->placeholder($account->customMessage($type))
										->label($type) !!}
							@endforeach
						</div>
					@endif
					<div role="tabpanel" class="tab-pane" id="classify">
						{!! Former::select('size_id')->addOption('','')
							->fromQuery($sizes, 'name', 'id') !!}
						{!! Former::select('industry_id')->addOption('','')
							->fromQuery($industries, 'name', 'id') !!}
					</div>
				</div>
		</div>
		</div>


		@if (Auth::user()->account->isNinjaAccount())
		<div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.pro_plan_product') !!}</h3>
          </div>
            <div class="panel-body">

				@if (isset($planDetails))
					{!! Former::populateField('plan', $planDetails['plan']) !!}
					{!! Former::populateField('plan_term', $planDetails['term']) !!}
					{!! Former::populateField('plan_price', $planDetails['plan_price']) !!}
					@if (!empty($planDetails['paid']))
						{!! Former::populateField('plan_paid', $planDetails['paid']->format('Y-m-d')) !!}
					@endif
					@if (!empty($planDetails['expires']))
						{!! Former::populateField('plan_expires', $planDetails['expires']->format('Y-m-d')) !!}
					@endif
					@if (!empty($planDetails['started']))
						{!! Former::populateField('plan_started', $planDetails['started']->format('Y-m-d')) !!}
					@endif
				@endif
				{!! Former::select('plan')
							->addOption(trans('texts.plan_free'), PLAN_FREE)
							->addOption(trans('texts.plan_pro'), PLAN_PRO)
							->addOption(trans('texts.plan_enterprise'), PLAN_ENTERPRISE)!!}
				{!! Former::select('plan_term')
							->addOption()
							->addOption(trans('texts.plan_term_yearly'), PLAN_TERM_YEARLY)
							->addOption(trans('texts.plan_term_monthly'), PLAN_TERM_MONTHLY)!!}
				{!! Former::text('plan_price') !!}
				{!! Former::text('plan_started')
                            ->data_date_format('yyyy-mm-dd')
                            ->addGroupClass('plan_start_date')
                            ->append('<i class="glyphicon glyphicon-calendar"></i>') !!}
                {!! Former::text('plan_paid')
                            ->data_date_format('yyyy-mm-dd')
                            ->addGroupClass('plan_paid_date')
                            ->append('<i class="glyphicon glyphicon-calendar"></i>') !!}
				{!! Former::text('plan_expires')
                            ->data_date_format('yyyy-mm-dd')
                            ->addGroupClass('plan_expire_date')
                            ->append('<i class="glyphicon glyphicon-calendar"></i>') !!}
                <script type="text/javascript">
                    $(function() {
                        $('#plan_started, #plan_paid, #plan_expires').datepicker();
                    });
                </script>

            </div>
            </div>
			@endif


		</div>
	</div>


	{!! Former::hidden('data')->data_bind("value: ko.toJSON(model)") !!}

	<script type="text/javascript">

	$(function() {
		$('#country_id, #shipping_country_id').combobox();

		// show/hide copy buttons if address is set
		$('#billing_address').change(function() {
			$('#copyBillingDiv').toggle(isAddressSet());
		});
		$('#shipping_address').change(function() {
			$('#copyShippingDiv').toggle(isAddressSet(true));
		});

		// button handles to copy the address
		$('#copyBillingDiv button').click(function() {
			copyAddress();
			$('#copyBillingDiv').hide();
		});
		$('#copyShippingDiv button').click(function() {
			copyAddress(true);
			$('#copyShippingDiv').hide();
		});

		// show/hide buttons based on loaded values
		if ({{ $client && $client->hasAddress() ? 'true' : 'false' }}) {
			$('#copyBillingDiv').show();
		}
		if ({{ $client && $client->hasAddress(true) ? 'true' : 'false' }}) {
			$('#copyShippingDiv').show();
		}
	});

	function copyAddress(shipping) {
		var fields = [
			'address1',
			'address2',
			'city',
			'state',
			'postal_code',
			'country_id',
		]
		for (var i=0; i<fields.length; i++) {
			var field1 = fields[i];
			var field2 = 'shipping_' + field1;
			if (shipping) {
				$('#' + field1).val($('#' + field2).val());
			} else {
				$('#' + field2).val($('#' + field1).val());
			}
		}
		$('#country_id').combobox('refresh');
		$('#shipping_country_id').combobox('refresh');
	}

	function isAddressSet(shipping) {
		var fields = [
			'address1',
			'address2',
			'city',
			'state',
			'postal_code',
			'country_id',
		]
		for (var i=0; i<fields.length; i++) {
			var field = fields[i];
			if (shipping) {
				field = 'shipping_' + field;
			}
			if ($('#' + field).val()) {
				return true;
			}
		}
		return false;
	}

	function ContactModel(data) {
		var self = this;
		self.public_id = ko.observable('');
		self.first_name = ko.observable('');
		self.last_name = ko.observable('');
		self.email = ko.observable('');
		self.phone = ko.observable('');
		self.password = ko.observable('');
		self.custom_value1 = ko.observable('');
		self.custom_value2 = ko.observable('');

		if (data) {
			ko.mapping.fromJS(data, {}, this);
		}
	}

	function ClientModel(data) {
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
				return (contact.first_name() || '') + ' ' + (contact.last_name() || '');
			} else {
				return contact.email();
			}
		});
	}

    @if ($data)
        window.model = new ClientModel({!! $data !!});
    @else
	    window.model = new ClientModel({!! $client !!});
    @endif

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
	@if(Auth::user()->canCreateOrEdit(ENTITY_CLIENT))
	<center class="buttons">
    	{!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/clients/' . ($client ? $client->public_id : '')))->appendIcon(Icon::create('remove-circle')) !!}
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
	</center>
	@endif
	{!! Former::close() !!}
</div>
@stop
