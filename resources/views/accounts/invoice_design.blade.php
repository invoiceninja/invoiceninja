@extends('header')

@section('head')
	@parent

    @include('money_script')
    @foreach ($account->getFontFolders() as $font)
        <script src="{{ asset('js/vfs_fonts/'.$font.'.js') }}" type="text/javascript"></script>
    @endforeach
    <script src="{{ asset('pdf.built.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>
    <script src="{{ asset('js/lightbox.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/lightbox.css') }}" rel="stylesheet" type="text/css"/>
@stop

@section('head_css')
	@parent

	<style type="text/css">
		.label-group {
			display: none;
		}
	</style>
@stop

@section('content')
	@parent
    @include('accounts.nav', ['selected' => ACCOUNT_INVOICE_DESIGN, 'advanced' => true])
    @include('accounts.partials.invoice_fields')

  <script>
    var invoiceDesigns = {!! $invoiceDesigns !!};
    var invoiceFonts = {!! $invoiceFonts !!};
    var invoice = {!! json_encode($invoice) !!};

    function getDesignJavascript() {
      var id = $('#invoice_design_id').val();
      if (id == '-1') {
        showMoreDesigns();
        $('#invoice_design_id').val(1);
        return invoiceDesigns[0].javascript;
      } else {
        var design = _.find(invoiceDesigns, function(design){ return design.id == id});
        return design ? design.javascript : '';
      }
    }

    function loadFont(fontId){
      var fontFolder = '';
      $.each(window.invoiceFonts, function(i, font){
        if(font.id==fontId)fontFolder=font.folder;
      });
      if(!window.ninjaFontVfs[fontFolder]){
        window.loadingFonts = true;
        jQuery.getScript({!! json_encode(asset('js/vfs_fonts/%s.js')) !!}.replace('%s', fontFolder), function(){window.loadingFonts=false;ninjaLoadFontVfs();refreshPDF()})
      }
    }

    function getPDFString(cb) {
      invoice.features = {
          customize_invoice_design:{{ Auth::user()->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN) ? 'true' : 'false' }},
          remove_created_by:{{ Auth::user()->hasFeature(FEATURE_REMOVE_CREATED_BY) ? 'true' : 'false' }},
          invoice_settings:{{ Auth::user()->hasFeature(FEATURE_INVOICE_SETTINGS) ? 'true' : 'false' }}
      };
      invoice.account.invoice_embed_documents = $('#invoice_embed_documents').is(":checked");
      invoice.account.hide_paid_to_date = $('#hide_paid_to_date').is(":checked");
      invoice.invoice_design_id = $('#invoice_design_id').val();
      invoice.account.page_size = $('#page_size option:selected').text();
      invoice.account.invoice_fields = ko.mapping.toJSON(model);

      NINJA.primaryColor = $('#primary_color').val();
      NINJA.secondaryColor = $('#secondary_color').val();
      NINJA.fontSize = parseInt($('#font_size').val());
      NINJA.headerFont = $('#header_font_id option:selected').text();
      NINJA.bodyFont = $('#body_font_id option:selected').text();

      var fields = {!! json_encode(App\Models\Account::$customLabels) !!};
      for (var i=0; i<fields.length; i++) {
        var field = fields[i];
        var val = $('#labels_' + field).val();
		if ( ! invoiceLabels[field + '_orig']) {
			invoiceLabels[field + '_orig'] = invoiceLabels[field];
		}
		invoiceLabels[field] = val || invoiceLabels[field + '_orig'];
      }

      generatePDF(invoice, getDesignJavascript(), true, cb);
    }

	function updateFieldLabels() {
		@foreach (App\Models\Account::$customLabels as $field)
			if ($('#labels_{{ $field }}').val()) {
				$('.{{ $field }}-label-group').show();
			} else {
				$('.{{ $field }}-label-group').hide();
			}
		@endforeach
	}

	function onFieldChange() {
		var $select = $('#label_field');
        var id = $select.val();
		$select.val(null).blur();
		$('.' + id + '-label-group').fadeIn();
		showUsedFields();
	}

	function showUsedFields() {
		$('#label_field > option').each(function(key, option) {
			var isUsed = $('#labels_' + option.value).is(':visible');
			$(this).css('color', isUsed ? '#888' : 'black');
		});
	}

    $(function() {
      var options = {
        preferredFormat: 'hex',
        disabled: {!! Auth::user()->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN) ? 'false' : 'true' !!},
        showInitial: false,
        showInput: true,
        allowEmpty: true,
        clickoutFiresChange: true,
      };

      $('#primary_color').spectrum(options);
      $('#secondary_color').spectrum(options);
      $('#header_font_id').change(function(){loadFont($('#header_font_id').val())});
      $('#body_font_id').change(function(){loadFont($('#body_font_id').val())});

	  updateFieldLabels();
      refreshPDF();
	  setTimeout(function() {
		showUsedFields();
	  }, 1);

    });

  </script>


  <div class="row">
    <div class="col-md-12">

      {!! Former::open()->addClass('warn-on-exit')->onchange('if(!window.loadingFonts)refreshPDF()') !!}

      {!! Former::populateField('invoice_design_id', $account->invoice_design_id) !!}
	  {!! Former::populateField('quote_design_id', $account->quote_design_id) !!}
      {!! Former::populateField('body_font_id', $account->getBodyFontId()) !!}
      {!! Former::populateField('header_font_id', $account->getHeaderFontId()) !!}
      {!! Former::populateField('font_size', $account->font_size) !!}
      {!! Former::populateField('page_size', $account->page_size) !!}
      {!! Former::populateField('invoice_embed_documents', intval($account->invoice_embed_documents)) !!}
      {!! Former::populateField('primary_color', $account->primary_color) !!}
      {!! Former::populateField('secondary_color', $account->secondary_color) !!}
      {!! Former::populateField('hide_paid_to_date', intval($account->hide_paid_to_date)) !!}
      {!! Former::populateField('all_pages_header', intval($account->all_pages_header)) !!}
      {!! Former::populateField('all_pages_footer', intval($account->all_pages_footer)) !!}
	  {!! Former::populateField('background_image_id', $account->background_image ? $account->background_image->public_id : null) !!}

          @foreach ($invoiceLabels as $field => $value)
          {!! Former::populateField("labels_{$field}", $value) !!}
        @endforeach

        <div style="display:none">
            {!! Former::text('invoice_fields_json')->data_bind('value: ko.mapping.toJSON(model)') !!}
		</div>


    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">{!! trans('texts.invoice_design') !!}</h3>
      </div>

        <div class="panel-body">
            <div role="tabpanel">
                <ul class="nav nav-tabs" role="tablist" style="border: none">
                    <li role="presentation" class="active"><a href="#general_settings" aria-controls="general_settings" role="tab" data-toggle="tab">{{ trans('texts.general_settings') }}</a></li>
                    <li role="presentation"><a href="#invoice_labels" aria-controls="invoice_labels" role="tab" data-toggle="tab">{{ trans('texts.invoice_labels') }}</a></li>
                    <li role="presentation"><a href="#invoice_fields" aria-controls="invoice_fields" role="tab" data-toggle="tab">{{ trans('texts.invoice_fields') }}</a></li>
					<li role="presentation"><a href="#product_fields" aria-controls="product_fields" role="tab" data-toggle="tab">{{ trans('texts.product_fields') }}</a></li>
                    <li role="presentation"><a href="#invoice_options" aria-controls="invoice_options" role="tab" data-toggle="tab">{{ trans('texts.invoice_options') }}</a></li>
                </ul>
            </div>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="general_settings">
                    <div class="panel-body">

                      <div class="row">
                        <div class="col-md-6">

						  {!! Former::select('invoice_design_id')
						  		  ->label('invoice_design')
                                  ->fromQuery($invoiceDesigns, 'name', 'id') !!}
						  {!! Former::select('quote_design_id')
						  		  ->label('quote_design')
                                  ->fromQuery($invoiceDesigns, 'name', 'id') !!}
                          {!! Former::select('body_font_id')
                                  ->fromQuery($invoiceFonts, 'name', 'id') !!}
                          {!! Former::select('header_font_id')
                                  ->fromQuery($invoiceFonts, 'name', 'id') !!}

                        </div>
                        <div class="col-md-6">

                        {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 6) }}
                        {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 6) }}

                          {!! Former::select('page_size')
                                  ->options($pageSizes) !!}

                          {!! Former::text('font_size')
                                ->type('number')
                                ->min('0')
                                ->step('1') !!}

                          {!! Former::text('primary_color') !!}
                          {!! Former::text('secondary_color') !!}


                        {{ Former::setOption('TwitterBootstrap3.labelWidths.large', 4) }}
                        {{ Former::setOption('TwitterBootstrap3.labelWidths.small', 4) }}

                        </div>
                      </div>

                      <div class="help-block" style="padding-top:16px">
                        {{ trans('texts.color_font_help') }}
                      </div>

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="invoice_labels">
                    <div class="panel-body">

                      <div class="row">
                        <div class="col-md-6">
							{!! Former::select('label_field')
									->placeholder('select_label')
									->label('label')
									->onchange('onFieldChange()')
									->options(array_combine(App\Models\Account::$customLabels, Utils::trans(App\Models\Account::$customLabels))) !!}
						</div>
						<div class="col-md-6">
							@foreach (App\Models\Account::$customLabels as $field)
								{!! Former::text('labels_' . $field)
										->label($field)
										->addGroupClass($field . '-label-group label-group') !!}
							@endforeach
                        </div>
                      </div>

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="invoice_fields">
                    <div class="panel-body">
                      <div class="row" id="invoiceFields">
                          @include('accounts.partials.invoice_fields_selector', ['section' => 'invoice_fields', 'fields' => INVOICE_FIELDS_INVOICE])
                          @include('accounts.partials.invoice_fields_selector', ['section' => 'client_fields', 'fields' => INVOICE_FIELDS_CLIENT])
                          @include('accounts.partials.invoice_fields_selector', ['section' => 'account_fields1', 'fields' => INVOICE_FIELDS_ACCOUNT])
                          @include('accounts.partials.invoice_fields_selector', ['section' => 'account_fields2', 'fields' => INVOICE_FIELDS_ACCOUNT])
                      </div>
                      <div class="row" style="padding-top:30px">
                          <div class="pull-left help-block">
							  {{ trans('texts.invoice_fields_help') }}
                          </div>
						  <div class="pull-right" style="padding-right:14px">
                              {!! Button::normal(trans('texts.reset'))->small()
                                    ->withAttributes(['onclick' => 'sweetConfirm(function() {
                                        resetInvoiceFields();
                                    })']) !!}
                          </div>
                      </div>
                    </div>
                </div>
				<div role="tabpanel" class="tab-pane" id="product_fields">
                    <div class="panel-body">
  						<div class="row" id="productFields">
                            @include('accounts.partials.invoice_fields_selector', ['section' => 'product_fields', 'fields' => INVOICE_FIELDS_PRODUCT, 'colWidth' => 6])
                            @include('accounts.partials.invoice_fields_selector', ['section' => 'task_fields', 'fields' => INVOICE_FIELDS_TASK, 'colWidth' => 6])
                        </div>
                        <div class="row" style="padding-top:30px">
                            <div class="pull-left help-block">
  							  {{ trans('texts.product_fields_help') }}
                            </div>
  						    <div class="pull-right" style="padding-right:14px">
                                {!! Button::normal(trans('texts.reset'))->small()
                                      ->withAttributes(['onclick' => 'sweetConfirm(function() {
                                          resetProductFields();
                                      })']) !!}
                            </div>
                        </div>
					</div>
				</div>
                <div role="tabpanel" class="tab-pane" id="invoice_options">
                    <div class="panel-body">

						@if (auth()->user()->isEnterprise())
							{!! Former::select('background_image_id')
									->label('background_image')
									->addOption('', '')
									->fromQuery(\App\Models\Document::scope()->proposalImages()->get(), function($model) { return $model->name . ' - ' . Utils::formatNumber($model->size / 1000, null, 1) . ' KB'; }, 'public_id')
									->help($account->isModuleEnabled(ENTITY_PROPOSAL)
											? trans('texts.background_image_help', ['link' => link_to('/proposals/create?show_assets=true', trans('texts.proposal_editor'), ['target' => '_blank'])])
											//: trans('texts.enable_proposals_for_background', ['link' => link_to('/settings/account_management', trans('texts.click_here'), ['target' => '_blank'])])
											: 'To upload a background image <a href="http://www.ninja.test/settings/account_management" target="_blank">click here</a> to enable the proposals module.' 
										) !!}
						@endif

						{!! Former::checkbox('hide_paid_to_date')->text(trans('texts.hide_paid_to_date_help'))->value(1) !!}
						{!! Former::checkbox('invoice_embed_documents')->text(trans('texts.invoice_embed_documents_help'))->value(1) !!}

						<br/>

						{!! Former::inline_radios('all_pages_header')
							->label(trans('texts.all_pages_header'))
							->radios([
								trans('texts.first_page') => ['value' => 0, 'name' => 'all_pages_header'],
								trans('texts.all_pages') => ['value' => 1, 'name' => 'all_pages_header'],
								])->check($account->all_pages_header) !!}

						{!! Former::inline_radios('all_pages_footer')
							->label(trans('texts.all_pages_footer'))
							->radios([
								trans('texts.last_page') => ['value' => 0, 'name' => 'all_pages_footer'],
								trans('texts.all_pages') => ['value' => 1, 'name' => 'all_pages_footer'],
								])->check($account->all_pages_footer) !!}

                    </div>
                </div>
            </div>
        </div>
    </div>

    <center class="buttons">
		{!! $account->getCustomDesign(CUSTOM_DESIGN1) ?
				DropdownButton::primary(trans('texts.customize'))
					->withContents($account->present()->customDesigns)
					->large()  :
	            Button::primary(trans('texts.customize'))
	                ->appendIcon(Icon::create('edit'))
	                ->asLinkTo(URL::to('/settings/customize_design') . '?design_id=' . CUSTOM_DESIGN1)
	                ->large() !!}
        {!! Auth::user()->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN) ?
                Button::success(trans('texts.save'))
                    ->submit()->large()
                    ->appendIcon(Icon::create('floppy-disk'))
                    ->withAttributes(['class' => 'save-button']) :
                false !!}
	</center>

      {!! Former::close() !!}

    </div>
  </div>


      @include('invoices.pdf', ['account' => Auth::user()->account, 'pdfHeight' => 800])


@stop
