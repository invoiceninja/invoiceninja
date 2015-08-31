@extends('accounts.nav')

@section('head')
    @parent

        <style type="text/css">
            .input-group-addon div.checkbox {
                display: inline;
            }
            span.input-group-addon {
                padding-right: 30px;
            }
        </style>
@stop

@section('content')	
	@parent
	@include('accounts.nav_advanced')

	{!! Former::open()->addClass('warn-on-exit') !!}
	{{ Former::populate($account) }}
	{{ Former::populateField('custom_invoice_taxes1', intval($account->custom_invoice_taxes1)) }}
	{{ Former::populateField('custom_invoice_taxes2', intval($account->custom_invoice_taxes2)) }}
    {{ Former::populateField('share_counter', intval($account->share_counter)) }}
    {{ Former::populateField('pdf_email_attachment', intval($account->pdf_email_attachment)) }}

<div class="row">
    <div class="col-md-6">


    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">{!! trans('texts.invoice_fields') !!}</h3>
      </div>
        <div class="panel-body">	
        {!! Former::text('custom_invoice_label1')->label(trans('texts.field_label'))
        		->append(Former::checkbox('custom_invoice_taxes1')->raw() . trans('texts.charge_taxes')) !!}
        {!! Former::text('custom_invoice_label2')->label(trans('texts.field_label'))
        		->append(Former::checkbox('custom_invoice_taxes2')->raw() . ' ' . trans('texts.charge_taxes')) !!}			
	   </div>
    </div>

    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">{!! trans('texts.client_fields') !!}</h3>
      </div>
        <div class="panel-body">    
	   {!! Former::text('custom_client_label1')->label(trans('texts.field_label')) !!}
	   {!! Former::text('custom_client_label2')->label(trans('texts.field_label')) !!}
	   </div>
    </div>


    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">{!! trans('texts.company_fields') !!}</h3>
      </div>
        <div class="panel-body">        
    	{!! Former::text('custom_label1')->label(trans('texts.field_label')) !!}
    	{!! Former::text('custom_value1')->label(trans('texts.field_value')) !!}
    	<p>&nbsp;</p>
    	{!! Former::text('custom_label2')->label(trans('texts.field_label')) !!}
    	{!! Former::text('custom_value2')->label(trans('texts.field_value')) !!}
        </div>
    </div>

    </div>
    <div class="col-md-6">

    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">{!! trans('texts.invoice_number') !!}</h3>
      </div>
        <div class="panel-body">        
    	{!! Former::text('invoice_number_prefix')->label(trans('texts.prefix')) !!}
    	{!! Former::text('invoice_number_counter')->label(trans('texts.counter')) !!}
        </div>
    </div>


    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">{!! trans('texts.quote_number') !!}</h3>
      </div>
        <div class="panel-body">        
    	{!! Former::text('quote_number_prefix')->label(trans('texts.prefix')) !!}
    	{!! Former::text('quote_number_counter')->label(trans('texts.counter'))
	   		->append(Former::checkbox('share_counter')->raw()->onclick('setQuoteNumberEnabled()') . ' ' . trans('texts.share_invoice_counter')) !!}
	   </div>
    </div>


    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">{!! trans('texts.pdf_settings') !!}</h3>
      </div>
        <div class="panel-body">        	   
       {!! Former::checkbox('pdf_email_attachment')->text(trans('texts.enable')) !!}
	</div>
    </div>
    </div>
    </div>
    
	@if (Auth::user()->isPro())
    <center>
	   {!! Button::success(trans('texts.save'))->large()->submit()->appendIcon(Icon::create('floppy-disk')) !!}
    </center>
	@else

	<script>
    $(function() {   
    	$('form.warn-on-exit input').prop('disabled', true);
    });
	</script>	
	@endif

	{!! Former::close() !!}


	<script type="text/javascript">

  	function setQuoteNumberEnabled() {
			var disabled = $('#share_counter').prop('checked');
			$('#quote_number_counter').prop('disabled', disabled);
			$('#quote_number_counter').val(disabled ? '' : '{!! $account->quote_number_counter !!}');			
		}

    $(function() {       	
    	setQuoteNumberEnabled();
    });    

	</script>


@stop

@section('onReady')
    $('#custom_invoice_label1').focus();
@stop