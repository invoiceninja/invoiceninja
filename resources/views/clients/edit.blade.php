@extends('header')

@section('onReady')
	$('input#name').focus();
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
        {!! Former::hidden('public_id') !!}
	@else
		{!! Former::populateField('invoice_number_counter', 1) !!}
		{!! Former::populateField('quote_number_counter', 1) !!}
		@if ($account->client_number_counter)
			{!! Former::populateField('id_number', $account->getNextNumber()) !!}
		@endif
	@endif

	<div class="row">
		<div class="col-md-6">


        <div class="panel panel-default" style="min-height: 380px">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.organization') !!}</h3>
          </div>
            <div class="panel-body">

			{!! Former::text('name')->data_bind("attr { placeholder: placeholderName }") !!}
			{!! Former::text('id_number')->placeholder($account->clientNumbersEnabled() ? $account->getNextNumber() : ' ') !!}
            {!! Former::text('vat_number') !!}
            {!! Former::text('website') !!}
			{!! Former::text('work_phone') !!}

			@if (Auth::user()->hasFeature(FEATURE_INVOICE_SETTINGS))
				@if ($customLabel1)
					{!! Former::text('custom_value1')->label($customLabel1) !!}
				@endif
				@if ($customLabel2)
					{!! Former::text('custom_value2')->label($customLabel2) !!}
				@endif
			@endif

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
						attr: {name: 'contacts[' + \$index() + '][password]'}")->autocomplete('new-password') !!}
			    @endif

				@if (Auth::user()->hasFeature(FEATURE_INVOICE_SETTINGS))
					@if ($account->custom_contact_label1)
						{!! Former::text('custom_contact1')->data_bind("value: custom_value1, valueUpdate: 'afterkeydown',
								attr: {name: 'contacts[' + \$index() + '][custom_value1]'}")
							->label($account->custom_contact_label1) !!}
					@endif
					@if ($account->custom_contact_label2)
						{!! Former::text('custom_contact2')->data_bind("value: custom_value2, valueUpdate: 'afterkeydown',
								attr: {name: 'contacts[' + \$index() + '][custom_value2]'}")
							->label($account->custom_contact_label2) !!}
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


        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.additional_info') !!}</h3>
          </div>
            <div class="panel-body">

            {!! Former::select('currency_id')->addOption('','')
                ->placeholder($account->currency ? $account->currency->name : '')
                ->fromQuery($currencies, 'name', 'id') !!}
            {!! Former::select('language_id')->addOption('','')
                ->placeholder($account->language ? trans('texts.lang_'.$account->language->name) : '')
                ->fromQuery($languages, 'name', 'id') !!}
			{!! Former::select('payment_terms')->addOption('','')
				->fromQuery(\App\Models\PaymentTerm::getSelectOptions(), 'name', 'num_days')
				->placeholder($account->present()->paymentTerms)
                ->help(trans('texts.payment_terms_help')) !!}
			{!! Former::select('size_id')->addOption('','')
				->fromQuery($sizes, 'name', 'id') !!}
			{!! Former::select('industry_id')->addOption('','')
				->fromQuery($industries, 'name', 'id') !!}
			{!! Former::textarea('public_notes') !!}
			{!! Former::textarea('private_notes') !!}
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
		$('#country_id').combobox();
	});

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
				return contact.first_name() + ' ' + contact.last_name();
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

	<center class="buttons">
    	{!! Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/clients/' . ($client ? $client->public_id : '')))->appendIcon(Icon::create('remove-circle')) !!}
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
	</center>

	{!! Former::close() !!}
</div>
@stop
