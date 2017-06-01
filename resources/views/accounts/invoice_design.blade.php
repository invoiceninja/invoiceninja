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
      invoice.account.hide_quantity = $('#hide_quantity').is(":checked");
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

      var fields = [
          'item',
          'description',
          'unit_cost',
          'quantity',
          'line_total',
          'terms',
          'balance_due',
          'partial_due'
      ];
      invoiceLabels.old = {};
      for (var i=0; i<fields.length; i++) {
        var field = fields[i];
        var val = $('#labels_' + field).val();
        if (invoiceLabels.old.hasOwnProperty(field)) {
            invoiceLabels.old[field] = invoiceLabels[field];
        }
        if (val) {
            invoiceLabels[field] = val;
        }
      }

      generatePDF(invoice, getDesignJavascript(), true, cb);
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

      refreshPDF();
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
      {!! Former::populateField('hide_quantity', intval($account->hide_quantity)) !!}
      {!! Former::populateField('hide_paid_to_date', intval($account->hide_paid_to_date)) !!}
      {!! Former::populateField('all_pages_header', intval($account->all_pages_header)) !!}
      {!! Former::populateField('all_pages_footer', intval($account->all_pages_footer)) !!}

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
                    <li role="presentation"><a href="#invoice_options" aria-controls="invoice_options" role="tab" data-toggle="tab">{{ trans('texts.invoice_options') }}</a></li>
                    <li role="presentation"><a href="#header_footer" aria-controls="header_footer" role="tab" data-toggle="tab">{{ trans('texts.header_footer') }}</a></li>
                </ul>
            </div>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="general_settings">
                    <div class="panel-body">

                      <div class="row">
                        <div class="col-md-6">

						  {!! Former::select('invoice_design_id')
						  		  ->label('default_design')
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
							{!! Former::text('labels_item')->label('item') !!}
							{!! Former::text('labels_description')->label('description') !!}
							{!! Former::text('labels_unit_cost')->label('unit_cost') !!}
							{!! Former::text('labels_quantity')->label('quantity') !!}
							{!! Former::text('labels_line_total')->label('line_total') !!}
							{!! Former::text('labels_terms')->label('terms') !!}
							{!! Former::text('labels_subtotal')->label('subtotal') !!}
						</div>
						<div class="col-md-6">
							{!! Former::text('labels_discount')->label('discount') !!}
							{!! Former::text('labels_paid_to_date')->label('paid_to_date') !!}
							{!! Former::text('labels_balance_due')->label('balance_due') !!}
							{!! Former::text('labels_partial_due')->label('partial_due') !!}
							{!! Former::text('labels_tax')->label('tax') !!}
							{!! Former::text('labels_po_number')->label('po_number') !!}
							{!! Former::text('labels_due_date')->label('due_date') !!}
                        </div>
                      </div>

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="invoice_fields">
                    <div class="panel-body">
                      <div class="row">
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
                              {!! Button::normal(trans('texts.reset'))
                                    ->withAttributes(['onclick' => 'sweetConfirm(function() {
                                        resetFields();
                                    })'])
                                    ->small() !!}
                          </div>
                      </div>
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="invoice_options">
                    <div class="panel-body">

                      {!! Former::checkbox('hide_quantity')->text(trans('texts.hide_quantity_help'))->value(1) !!}
                      {!! Former::checkbox('hide_paid_to_date')->text(trans('texts.hide_paid_to_date_help'))->value(1) !!}
                      {!! Former::checkbox('invoice_embed_documents')->text(trans('texts.invoice_embed_documents_help'))->value(1) !!}

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="header_footer">
                    <div class="panel-body">

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


    <br/>
    {!! Former::actions(
			$account->getCustomDesign(CUSTOM_DESIGN1) ?
				DropdownButton::primary(trans('texts.customize'))
					->withContents($account->present()->customDesigns)
					->large()  :
	            Button::primary(trans('texts.customize'))
	                ->appendIcon(Icon::create('edit'))
	                ->asLinkTo(URL::to('/settings/customize_design') . '?design_id=' . CUSTOM_DESIGN1)
	                ->large(),
            Auth::user()->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN) ?
                Button::success(trans('texts.save'))
                    ->submit()->large()
                    ->appendIcon(Icon::create('floppy-disk'))
                    ->withAttributes(['class' => 'save-button']) :
                false
        ) !!}
    <br/>

      {!! Former::close() !!}

    </div>
  </div>


      @include('invoices.pdf', ['account' => Auth::user()->account, 'pdfHeight' => 800])


@stop
