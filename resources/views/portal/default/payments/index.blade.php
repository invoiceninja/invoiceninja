@extends('portal.default.layouts.master')
@section('header')
	@parent
    <link href="//cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css" rel="stylesheet" type="text/css"/>
@stop
@section('body')
    <main class="main">
        <div class="container-fluid">

    		<div class="row" style="padding-top: 30px;">
			
				<div class="col-lg-12" style="padding-bottom: 10px;">

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
@endpush
@section('footer')
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
        	url: '{!! route('client.payments.index') !!}',
	        data: function(data) { 
	        } 

        },
        drawCallback: function(settings){

           data = this.api().ajax.json().data;

        },
        columns: [

            {data: 'payment_date', name: 'payment_date', title: '{{ctrans('texts.payment_date')}}', visible: true},
            {data: 'payment_type_id', name: 'payment_type_id', title: '{{ctrans('texts.payment_type_id')}}', visible: true},
            {data: 'amount', name: 'amount', title: '{{ctrans('texts.amount')}}', visible: true},
            {data: 'transaction_reference', name: 'transaction_reference', title: '{{ctrans('texts.transaction_reference')}}', visible: true},
            {data: 'status_id', name: 'status_id', title: '{{ctrans('texts.status')}}', visible: true},
            {data: 'action', name: 'action', title: '', searchable: false, orderable: false}

        ]
    });
});

</script>
@endsection

