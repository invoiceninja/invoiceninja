@extends('portal.default.layouts.master')
@section('header')
	@parent
    <link href="//cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css" rel="stylesheet" type="text/css"/>
    <link href="/vendors/css/select2.min.css" rel="stylesheet">
@stop
@section('body')
<main class="main">
    <div class="container-fluid">

{!! Former::framework('TwitterBootstrap4'); !!}

{!! Former::horizontal_open()
    ->id('payment_form')
    ->route('client.invoices.bulk')
    ->method('POST');    !!}

{!! Former::hidden('hashed_ids')->id('hashed_ids') !!}
{!! Former::hidden('action')->id('action') !!}

{!! Former::close() !!}

		<div class="row" style="padding-top: 30px;">
		
			<div class="col-lg-12" style="padding-bottom: 10px;">

				<!-- Filters / Buttons in here.-->
				<div id="top_right_buttons" class="pull-right">
                      <a href="{{ route('client.payment_methods.create ')}}" class="btn btn-success">{{ ctrans('texts.add_payment_method') }}</button>
				</div>

				<div class="animated fadeIn">
                    <div class="col-md-12 card">

					{!! $html->table(['class' => 'table table-hover table-striped', 'id' => 'datatable'], true) !!}

                    </div>
                </div>
			</div>

		</div>

    </div>
</main>
</body>
@endsection
@push('scripts')
	<script src="//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js" type="text/javascript"></script>
    <script src="//cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js"></script>
<script>

/*global json payload*/
var data;

var data_table;

$(function() {

    data_table = $('#datatable').DataTable({
        processing: true,
        serverSide: true,
        searching: false,
        bLengthChange: false,
        language: {
	        processing:     " {{ trans('texts.processing_request') }}",
	        search:         "{{ trans('texts.search') }}:",
	       // info:           "{{ trans('texts.info') }}",
	        infoPostFix:    "",
	        loadingRecords: "{{ trans('texts.loading') }}",
	        zeroRecords:    "{{ trans('texts.no_records_found') }}"
    	},
        ajax: {
        	url: '{!! route('client.payment_methods.index') !!}',
	        data: function(data) { 
	        } 

        },
        drawCallback: function(settings){

           data = this.api().ajax.json().data;

        },
        columns: [

            {data: 'invoice_number', name: 'invoice_number', title: '{{ctrans('texts.invoice_number')}}', visible: true},
            {data: 'invoice_date', name: 'invoice_date', title: '{{ctrans('texts.invoice_date')}}', visible: true},
            {data: 'amount', name: 'amount', title: '{{ctrans('texts.total')}}', visible: true},
            {data: 'balance', name: 'balance', title: '{{ctrans('texts.balance')}}', visible: true},
            {data: 'due_date', name: 'due_date', title: '{{ctrans('texts.due_date')}}', visible: true},
            {data: 'status_id', name: 'status_id', title: '{{ctrans('texts.status')}}', visible: true},
            {data: 'action', name: 'action', title: '', searchable: false, orderable: false},
        ]
    });
});

</script>

@endpush

