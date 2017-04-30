@extends('public.header')

@section('content')

	<style type="text/css">
        table.dataTable thead > tr > th, table.invoice-table thead > tr > th {
            background-color: {{ $color }} !important;
        }

        .pagination>.active>a,
        .pagination>.active>span,
        .pagination>.active>a:hover,
        .pagination>.active>span:hover,
        .pagination>.active>a:focus,
        .pagination>.active>span:focus {
            background-color: {{ $color }};
            border-color: {{ $color }};
        }

        table.table thead .sorting:after { content: '' !important }
        table.table thead .sorting_asc:after { content: '' !important }
        table.table thead .sorting_desc:after { content: '' !important }
        table.table thead .sorting_asc_disabled:after { content: '' !important }
        table.table thead .sorting_desc_disabled:after { content: '' !important }

	</style>

	<div class="container" id="main-container">

		<p>&nbsp;</p>

		<!--
		<div id="top_right_buttons" class="pull-right">
			<input id="tableFilter" type="text" style="width:140px;margin-right:17px" class="form-control pull-left" placeholder="{{ trans('texts.filter') }}"/>
		</div>
		-->

        @if($entityType == ENTITY_INVOICE && $client->hasRecurringInvoices())
            <div class="pull-right" style="margin-top:5px">
                {!! Button::primary(trans("texts.recurring_invoices"))->asLinkTo(URL::to('/client/invoices/recurring')) !!}
            </div>
        @endif
        <h3>{{ $title }}</h3>

		{!! Datatable::table()
	    	->addColumn($columns)
	    	->setUrl(route('api.client.' . $entityType . 's'))
	    	->setOptions('sPaginationType', 'bootstrap')
	    	->render('datatable') !!}
	</div>

    @if ($entityType == ENTITY_RECURRING_INVOICE)
        {!! Former::open(URL::to('/client/invoices/auto_bill'))->id('auto_bill_form')  !!}
        <input type="hidden" name="public_id" id="auto_bill_public_id">
        <input type="hidden" name="enable" id="auto_bill_enable">
        {!! Former::close() !!}

        <script type="text/javascript">
            function setAutoBill(publicId, enable){
                $('#auto_bill_public_id').val(publicId);
                $('#auto_bill_enable').val(enable?'1':'0');
                $('#auto_bill_form').submit();
            }
        </script>
    @endif


	<p>&nbsp;</p>

@stop
