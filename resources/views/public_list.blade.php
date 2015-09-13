@extends('public.header')

@section('content')
	
	<style type="text/css">

		body {
			background-color: #f8f8f8;
		}

		table.dataTable { border-radius: 3px; border-collapse: collapse;
		/*border-spacing: 0;*/}
		table.dataTable thead > tr > th, table.invoice-table thead > tr > th {
			background-color: {{ $color }} !important;
			color:#fff;
		}
		th:first-child {
			border-radius: 3px 0 0 0;
			border-left: none;
		}
		th:last-child {
			border-radius: 0 3px 0 0;
		}

		tr {border: none;}
		td {
			padding-top: 16px !important;
			padding-bottom: 16px !important;
		}

		/*th {border-left: 1px solid #d26b26; }*/
		th {border-left: 1px solid #FFFFFF; }
		.table>thead>tr>th, .table>tbody>tr>th, .table>tfoot>tr>th, .table>thead>tr>td, .table>tbody>tr>td, .table>tfoot>tr>td {
			vertical-align: middle;
			border-top: none;
			border-bottom: 1px solid #dfe0e1;
		}
		table.dataTable.no-footer {
			border-bottom: none;
		}
		.table-striped>tbody>tr:nth-child(odd)>td, 
		.table-striped>tbody>tr:nth-child(odd)>th {
			background-color: #FDFDFD;
		}
		table.table thead .sorting_asc {
			background: url('../images/sort_asc.png') no-repeat 90% 50%;
		}
		table.table thead .sorting_desc {
			background: url('../images/sort_desc.png') no-repeat 90% 50%;
		}
		table.dataTable thead th, table.dataTable thead td, table.invoice-table thead th, table.invoice-table thead td {
			padding: 12px 10px;
		}
		table.dataTable tbody th, table.dataTable tbody td {
			padding: 10px;
		}

		.dataTables_wrapper {
			padding-top: 16px;
		}

		table.table thead > tr > th {
			border-bottom-width: 0px;
		}

		table td {
			max-width: 250px;
		}
		.pagination>.active>a, .pagination>.active>span, .pagination>.active>a:hover, .pagination>.active>span:hover, .pagination>.active>a:focus, .pagination>.active>span:focus {
			background-color: {{ $color }};
			border-color: {{ $color }};
		}
		.pagination>li:first-child>a, .pagination>li:first-child>span {
			border-bottom-left-radius: 3px;
			border-top-left-radius: 3px;
		}

		/* hide table sorting indicators */
		table.table thead .sorting { background: url('') no-repeat center right; }

	</style>

	<div class="container" id="main-container">

		<p>&nbsp;</p>

		<!--
		<div id="top_right_buttons" class="pull-right">
			<input id="tableFilter" type="text" style="width:140px;margin-right:17px" class="form-control pull-left" placeholder="{{ trans('texts.filter') }}"/>       
		</div>
		-->

		<h2>{{ $title }}</h2>

		{!! Datatable::table()
	    	->addColumn($columns)
	    	->setUrl(route('api.client.' . $entityType . 's'))    	
	    	->setOptions('sPaginationType', 'bootstrap')
	    	->render('datatable') !!}

	</div>

    <p>&nbsp;</p>
    <p>&nbsp;</p>

    <script type="text/javascript">
        $(function() {
            $('#main-container').height($(window).height() - ($('.navbar').height() + $('footer').height() + 20));
        });
    </script>

@stop

