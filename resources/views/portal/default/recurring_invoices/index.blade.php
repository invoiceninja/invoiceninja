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
        	url: '{!! route('client.recurring_invoices.index') !!}',
	        data: function(data) { 
	        } 

        },
        drawCallback: function(settings){

           data = this.api().ajax.json().data;

        },
        columns: [

            {data: 'frequency_id', name: 'frequency_id', title: '{{trans('texts.frequency')}}', visible: true},
            {data: 'start_date', name: 'start_date', title: '{{trans('texts.start_date')}}', visible: true},
            {data: 'next_send_date', name: 'next_send_date', title: '{{trans('texts.next_send_date')}}', visible: true},
            {data: 'remaining_cycles', name: 'remaining_cycles', title: '{{trans('texts.cycles_remaining')}}', visible: true},
            {data: 'amount', name: 'amount', title: '{{trans('texts.amount')}}', visible: true},
            {data: 'action', name: 'action', title: '', searchable: false, orderable: false},

        ]
    });
});

</script>
@endsection

