@extends('header')

@section('head')
    @parent

        <style type="text/css">
            .iframe_url {
                display: none;
            }
            .input-group-addon div.checkbox {
                display: inline;
            }
            .tab-content span.input-group-addon {
                padding-right: 30px;
            }
        </style>
@stop

@section('content')	
	@parent
    @include('accounts.nav', ['selected' => ACCOUNT_INVOICE_SETTINGS, 'advanced' => true])

	{!! Former::open()->addClass('warn-on-exit') !!}
	{{ Former::populate($account) }}
	{{ Former::populateField('custom_invoice_taxes1', intval($account->custom_invoice_taxes1)) }}
	{{ Former::populateField('custom_invoice_taxes2', intval($account->custom_invoice_taxes2)) }}
    {{ Former::populateField('share_counter', intval($account->share_counter)) }}
    {{ Former::populateField('pdf_email_attachment', intval($account->pdf_email_attachment)) }}


    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.email_settings') !!}</h3>
        </div>
        <div class="panel-body form-padding-right">
            {!! Former::checkbox('pdf_email_attachment')->text(trans('texts.enable')) !!}
            @if (Utils::isNinja())
                {!! Former::inline_radios('custom_invoice_link')
                        ->onchange('onCustomLinkChange()')
                        ->radios([
                            trans('texts.subdomain') => ['value' => 'subdomain', 'name' => 'custom_link'],
                            trans('texts.website') => ['value' => 'website', 'name' => 'custom_link'],
                        ])->check($account->iframe_url ? 'website' : 'subdomain') !!}
                {{ Former::setOption('capitalize_translations', false) }}
                {!! Former::text('subdomain')
                            ->placeholder(trans('texts.www'))
                            ->onchange('onSubdomainChange()')
                            ->addGroupClass('subdomain')
                            ->label(' ') !!}
                {!! Former::text('iframe_url')
                            ->placeholder('http://www.example.com/invoice')
                            ->appendIcon('question-sign')
                            ->addGroupClass('iframe_url')
                            ->label(' ') !!}
            @endif
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.invoice_quote_number') !!}</h3>
        </div>
        <div class="panel-body form-padding-right">
            <div role="tabpanel">
                <ul class="nav nav-tabs" role="tablist" style="border: none">
                    <li role="presentation" class="active"><a href="#invoiceNumber" aria-controls="invoiceNumber" role="tab" data-toggle="tab">{{ trans('texts.invoice_number') }}</a></li>
                    <li role="presentation"><a href="#quoteNumber" aria-controls="quoteNumber" role="tab" data-toggle="tab">{{ trans('texts.quote_number') }}</a></li>
                </ul>
            </div>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="invoiceNumber">
                    <div class="panel-body">
                        {!! Former::text('invoice_number_prefix')->label(trans('texts.prefix')) !!}
                        {!! Former::text('invoice_number_counter')->label(trans('texts.counter')) !!}
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="quoteNumber">
                    <div class="panel-body">
                        {!! Former::text('quote_number_prefix')->label(trans('texts.prefix')) !!}
                        {!! Former::text('quote_number_counter')->label(trans('texts.counter'))
                        ->append(Former::checkbox('share_counter')->raw()->onclick('setQuoteNumberEnabled()') . ' ' . trans('texts.share_invoice_counter')) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.custom_fields') !!}</h3>
        </div>
        <div class="panel-body form-padding-right">

            <div role="tabpanel">
                <ul class="nav nav-tabs" role="tablist" style="border: none">
                    <li role="presentation" class="active"><a href="#clientFields" aria-controls="clientFields" role="tab" data-toggle="tab">{{ trans('texts.client_fields') }}</a></li>
                    <li role="presentation"><a href="#companyFields" aria-controls="companyFields" role="tab" data-toggle="tab">{{ trans('texts.company_fields') }}</a></li>
                    <li role="presentation"><a href="#invoiceFields" aria-controls="invoiceFields" role="tab" data-toggle="tab">{{ trans('texts.invoice_fields') }}</a></li>
                    <li role="presentation"><a href="#invoiceCharges" aria-controls="invoiceCharges" role="tab" data-toggle="tab">{{ trans('texts.invoice_charges') }}</a></li>
                </ul>
            </div>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="clientFields">
                    <div class="panel-body">

                        {!! Former::text('custom_client_label1')->label(trans('texts.field_label')) !!}
                        {!! Former::text('custom_client_label2')->label(trans('texts.field_label')) !!}

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="companyFields">
                    <div class="panel-body">

                        {!! Former::text('custom_label1')->label(trans('texts.field_label')) !!}
                        {!! Former::text('custom_value1')->label(trans('texts.field_value')) !!}
                        <p>&nbsp;</p>
                        {!! Former::text('custom_label2')->label(trans('texts.field_label')) !!}
                        {!! Former::text('custom_value2')->label(trans('texts.field_value')) !!}

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="invoiceFields">
                    <div class="panel-body">

                        {!! Former::text('custom_invoice_text_label1')->label(trans('texts.field_label')) !!}
                        {!! Former::text('custom_invoice_text_label2')->label(trans('texts.field_label')) !!}

                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="invoiceCharges">
                    <div class="panel-body">

                        {!! Former::text('custom_invoice_label1')->label(trans('texts.field_label'))
                        ->append(Former::checkbox('custom_invoice_taxes1')->raw() . trans('texts.charge_taxes')) !!}
                        {!! Former::text('custom_invoice_label2')->label(trans('texts.field_label'))
                        ->append(Former::checkbox('custom_invoice_taxes2')->raw() . trans('texts.charge_taxes')) !!}

                    </div>
                </div>
            </div>
        </div>
    </div>

    
    @if (Auth::user()->isPro())
        <center>
            {!! Button::success(trans('texts.save'))->large()->submit()->appendIcon(Icon::create('floppy-disk')) !!}
        </center>
    @endif


    <div class="modal fade" id="iframeHelpModal" tabindex="-1" role="dialog" aria-labelledby="iframeHelpModalLabel" aria-hidden="true">
        <div class="modal-dialog" style="min-width:150px">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="iframeHelpModalLabel">{{ trans('texts.iframe_url') }}</h4>
                </div>

                <div class="modal-body">
                    <p>{{ trans('texts.iframe_url_help1') }}</p>
                    <pre>&lt;center&gt;
    &lt;iframe id="invoiceIFrame" width="1000" height="1200"&gt;&lt;/iframe&gt;
&lt;center&gt;
&lt;script language="javascript"&gt;
    var iframe = document.getElementById('invoiceIFrame');
    iframe.src = '{{ SITE_URL }}/view/' 
                 + window.location.search.substring(1);
&lt;/script&gt;</pre>
                    <p>{{ trans('texts.iframe_url_help2') }}</p>
                    </div>

                <div class="modal-footer" style="margin-top: 0px">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">{{ trans('texts.close') }}</button>
                </div>

            </div>
        </div>
    </div>


	{!! Former::close() !!}


	<script type="text/javascript">

  	function setQuoteNumberEnabled() {
			var disabled = $('#share_counter').prop('checked');
			$('#quote_number_counter').prop('disabled', disabled);
			$('#quote_number_counter').val(disabled ? '' : '{!! $account->quote_number_counter !!}');			
		}

    function onSubdomainChange() {
        var input = $('#subdomain');
        var val = input.val();
        if (!val) return;
        val = val.replace(/[^a-zA-Z0-9_\-]/g, '').toLowerCase().substring(0, {{ MAX_SUBDOMAIN_LENGTH }});
        input.val(val);
    }

    function onCustomLinkChange() {
        var val = $('input[name=custom_link]:checked').val()
        if (val == 'subdomain') {
            $('.subdomain').show();
            $('.iframe_url').hide();
        } else {
            $('.subdomain').hide();
            $('.iframe_url').show();
        }
    }

    $('.iframe_url .input-group-addon').click(function() {
        $('#iframeHelpModal').modal('show');
    });

    $(function() {       	
    	setQuoteNumberEnabled();
        onCustomLinkChange();

        $('#subdomain').change(function() {
            $('#iframe_url').val('');
        });
        $('#iframe_url').change(function() {
            $('#subdomain').val('');
        });
    });    

	</script>


@stop

@section('onReady')
    $('#custom_invoice_label1').focus();
@stop