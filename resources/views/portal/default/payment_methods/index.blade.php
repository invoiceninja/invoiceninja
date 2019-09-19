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
                      <a href="{{ route('client.payment_methods.create')}}" class="btn btn-success">{{ ctrans('texts.add_payment_method') }}</a>
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
        columns: [

            {data: 'created_at', name: 'created_at', title: '{{ctrans('texts.created_at')}}', visible: true},
            {data: 'gateway_type_id', name: 'gateway_type_id', title: '{{ctrans('texts.payment_type_id')}}', visible: true},
            {data: 'brand', name: 'brand', title: '{{ctrans('texts.type')}}', visible: true},
            {data: 'meta', name: 'meta', title: '{{ctrans('texts.expires')}}', visible: true},
            {data: 'last4', name: 'last4', title: '{{ctrans('texts.card_number')}}', visible: true},
            {data: 'is_default', name: 'is_default', title: '{{ctrans('texts.default')}}', visible: true},
            {data: 'action', name: 'action', title: '', searchable: false, orderable: false},
        ]
    });
});

</script>

@endpush