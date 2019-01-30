@extends('header')

@section('head')
	@parent

    @include('money_script')
        <link href="{{ asset('css/jsoneditor.min.css') }}" rel="stylesheet" type="text/css">
        <script src="{{ asset('js/jsoneditor.min.js') }}" type="text/javascript"></script>

    @foreach ($account->getFontFolders() as $font)
        <script src="{{ asset('js/vfs_fonts/'.$font.'.js') }}" type="text/javascript"></script>
    @endforeach
        <script src="{{ asset('pdf.built.js') }}?no_cache={{ NINJA_VERSION }}" type="text/javascript"></script>

      <style type="text/css">

        select.form-control {
            background: #FFFFFF !important;
            margin-right: 12px;
        }
        table {
            background: #FFFFFF !important;
        }

        /* http://stackoverflow.com/questions/4810841/how-can-i-pretty-print-json-using-javascript */
        pre {outline: 1px solid #ccc; padding: 5px; margin: 5px; }
        .string { color: green; }
        .number { color: red; }
        .boolean { color: blue; }
        .null { color: gray; }
        .key { color: black; }

      </style>

@stop

@section('content')
    @parent

  <script>
    var invoiceDesigns = {!! $invoiceDesigns !!};
    var invoiceFonts = {!! $invoiceFonts !!};
    var invoice = {!! json_encode($invoice) !!};
    var sections = ['content', 'styles', 'defaultStyle', 'pageMargins', 'header', 'footer', 'background'];
    var customDesign = origCustomDesign = {!! $customDesign ?: 'JSON.parse(invoiceDesigns[0].javascript);' !!};

    function getPDFString(cb, force) {
      invoice.invoice_design_id = $('#invoice_design_id').val();
      invoice.features = {
            customize_invoice_design:{{ Auth::user()->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN) ? 'true' : 'false' }},
            remove_created_by:{{ Auth::user()->hasFeature(FEATURE_REMOVE_CREATED_BY) ? 'true' : 'false' }},
            invoice_settings:{{ Auth::user()->hasFeature(FEATURE_INVOICE_SETTINGS) ? 'true' : 'false' }}
        };
      invoice.account.hide_paid_to_date = {!! Auth::user()->account->hide_paid_to_date ? 'true' : 'false' !!};
      NINJA.primaryColor = {!! json_encode(Auth::user()->account->primary_color) !!};
      NINJA.secondaryColor = {!! json_encode(Auth::user()->account->secondary_color) !!};
      NINJA.fontSize = {!! Auth::user()->account->font_size !!};
      NINJA.headerFont = {!! json_encode(Auth::user()->account->getHeaderFontName()) !!};
      NINJA.bodyFont = {!! json_encode(Auth::user()->account->getBodyFontName()) !!};

      generatePDF(invoice, getDesignJavascript(), force, cb);
    }

    function getDesignJavascript() {
      var id = $('#invoice_design_id').val();
      if (id == '-1') {
        showMoreDesigns();
        $('#invoice_design_id').val(1);
        return invoiceDesigns[0].javascript;
      } else if (customDesign) {
        return JSON.stringify(customDesign);
      } else {
        return invoiceDesigns[0].javascript;
      }
    }

    function loadEditor(section)
    {
		if (section == 'defaults') {
			section = 'defaultStyle';
		} else if (section == 'margins') {
			section = 'pageMargins';
		}

        editorSection = section;
        editor.set(customDesign[section]);

        // the function throws an error if the editor is in code view
        try {
            editor.expandAll();
        } catch(err) {}
    }

    function saveEditor(data)
    {
        setTimeout(function() {
            customDesign[editorSection] = editor.get();
            clearError();
            refreshPDF();
        }, 100)
    }

    function onSelectChange()
    {
		var $select = $('#invoice_design_id');
        var id = $select.val();
		$select.val(null).blur();

        if (parseInt(id)) {
            var design = _.find(invoiceDesigns, function(design){ return design.id == id});
            customDesign = JSON.parse(design.javascript);
        } else {
            customDesign = origCustomDesign;
        }

        loadEditor(editorSection);
        clearError();
        refreshPDF(true);
    }

    function submitForm()
    {
        if (!NINJA.isPDFValid) {
            return;
        }

        $('#custom_design').val(JSON.stringify(customDesign));
        $('form.warn-on-exit').submit();
    }

    window.onerror = function(e) {
        $('#pdf-error').html(e.message ? e.message : e).show();
        $('button.save-button').prop('disabled', true);
        NINJA.isPDFValid = false;
    }

    function clearError() {
        NINJA.isPDFValid = true;
        $('#pdf-error').hide();
        $('button.save-button').prop('disabled', false);
    }

    $(function() {
       clearError();

        var container = document.getElementById("jsoneditor");
          var options = {
            mode: 'form',
            modes: ['form', 'code'],
            change: function() {
              saveEditor();
			  NINJA.formIsChanged = true;
            }
          };
        window.editor = new JSONEditor(container, options);
        loadEditor('content');

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
          var target = $(e.target).attr("href") // activated tab
          target = target.substring(1); // strip leading #
          loadEditor(target);
        });

        refreshPDF(true);
    });

  </script>


  <div class="row">
    <div class="col-md-6">

      {!! Former::open()->addClass('warn-on-exit') !!}

        <div style="display:none">
            {!! Former::text('custom_design') !!}
        </div>


      <div role="tabpanel">
        <ul class="nav nav-tabs" role="tablist" style="border: none">
            <li role="presentation" class="active"><a href="#content" aria-controls="content" role="tab" data-toggle="tab">{{ trans('texts.content') }}</a></li>
            <li role="presentation"><a href="#styles" aria-controls="styles" role="tab" data-toggle="tab">{{ trans('texts.styles') }}</a></li>
            <li role="presentation"><a href="#defaults" aria-controls="defaults" role="tab" data-toggle="tab">{{ trans('texts.defaults') }}</a></li>
            <li role="presentation"><a href="#margins" aria-controls="margins" role="tab" data-toggle="tab">{{ trans('texts.margins') }}</a></li>
            <li role="presentation"><a href="#header" aria-controls="header" role="tab" data-toggle="tab">{{ trans('texts.header') }}</a></li>
            <li role="presentation"><a href="#footer" aria-controls="footer" role="tab" data-toggle="tab">{{ trans('texts.footer') }}</a></li>
			@if ($account->isEnterprise() && $account->background_image_id)
				<li role="presentation"><a href="#background" aria-controls="footer" role="tab" data-toggle="tab">{{ trans('texts.background') }}</a></li>
			@endif
        </ul>
    </div>
    <div id="jsoneditor" style="width: 100%; height: 814px;"></div>
    <p>&nbsp;</p>

    <div>
    {!! Former::select('invoice_design_id')
			->placeholder(trans('texts.load_design'))
			->style('display:inline;width:180px')
			->fromQuery($invoiceDesigns, 'name', 'id')
			->onchange('onSelectChange()')
			->raw() !!}
    <div class="pull-right">
        {!! Button::normal(trans('texts.help'))->withAttributes(['onclick' => 'showHelp()'])->appendIcon(Icon::create('question-sign')) !!}
        {!! Button::normal(trans('texts.cancel'))->asLinkTo(URL::to('/settings/invoice_design'))->appendIcon(Icon::create('remove-circle')) !!}
        @if (Auth::user()->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN))
            {!! Button::success(trans('texts.save'))->withAttributes(['onclick' => 'submitForm()'])->appendIcon(Icon::create('floppy-disk'))->withAttributes(['class' => 'save-button']) !!}
        @endif
    </div>
    </div>

      <script>

        function showHelp() {
            $('#designHelpModal').modal('show');
        }

      </script>

      {!! Former::close() !!}


    <div class="modal fade" id="designHelpModal" tabindex="-1" role="dialog" aria-labelledby="designHelpModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
            <h4 class="modal-title" id="designHelpModalLabel">{{ trans('texts.help') }}</h4>
          </div>

		  <div class="container" style="width: 100%; padding-bottom: 0px !important">
		  <div class="panel panel-default">
		  <div class="panel-body">
	            {!! trans('texts.customize_help', [
						'pdfmake_link' => link_to('http://pdfmake.org', 'pdfmake', ['target' => '_blank']),
						'playground_link' => link_to('http://pdfmake.org/playground.html', trans('texts.playground'), ['target' => '_blank']),
						'forum_link' => link_to('https://www.invoiceninja.com/forums/forum/support', trans('texts.support_forum'), ['target' => '_blank']),
					]) !!}<br/>

				@include('partials/variables_help', ['entityType' => ENTITY_INVOICE, 'account' => $account])
          </div>
	  	  </div>
  		  </div>

         <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.close') }}</button>
			<a class="btn btn-primary" href="{{ config('ninja.video_urls.custom_design') }}" target="_blank">{{ trans('texts.video') }}</a>
         </div>

        </div>
      </div>
    </div>



    </div>
    <div class="col-md-6">
      <div id="pdf-error" class="alert alert-danger" style="display:none"></div>

      @include('invoices.pdf', ['account' => Auth::user()->account, 'pdfHeight' => 930])

    </div>
  </div>

@stop
