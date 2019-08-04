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
					
                    <div class="pull-left">

                            {!! Former::dark_button(ctrans('texts.download'))->addClass('download_invoices') !!}
                            {!! Former::success_button(ctrans('texts.pay_now'))->addClass('pay_invoices') !!}

                    </div>

					<!-- Filters / Buttons in here.-->
					<div id="top_right_buttons" class="pull-right">

						<input id="table_filter" type="text" style="width:180px;background-color: white !important"
					        class="form-control pull-left" placeholder="{{ trans('texts.filter')}}" value=""/>
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
@endpush

@section('footer')
<script>

/*global json payload*/
var data;

/*status filter variable - comma separated*/
var client_statuses;

var table_filter;

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
        	url: '{!! route('client.invoices.index') !!}',
	        data: function(data) { 
	        	data.client_status = client_statuses; 
                data.filter = table_filter;
              //  data.search.value = table_filter;
	        } 

        },
        drawCallback: function(settings){

           data = this.api().ajax.json().data;

        },
        columns: [

            {data: 'checkbox', name: 'checkbox', title: '<input type="checkbox" class="select_all">', searchable: false, orderable: false},
            {data: 'invoice_number', name: 'invoice_number', title: '{{trans('texts.invoice_number')}}', visible: true},
            {data: 'invoice_date', name: 'invoice_date', title: '{{trans('texts.invoice_date')}}', visible: true},
            {data: 'amount', name: 'amount', title: '{{trans('texts.total')}}', visible: true},
            {data: 'balance', name: 'balance', title: '{{trans('texts.balance')}}', visible: true},
            {data: 'due_date', name: 'due_date', title: '{{trans('texts.due_date')}}', visible: true},
            {data: 'status_id', name: 'status_id', title: '{{trans('texts.status')}}', visible: true},
            {data: 'action', name: 'action', title: '', searchable: false, orderable: false},
        ]
    });
});

</script>

<script>

$(document).ready(function() {

    var searchTimeout = false;

    $('.pay_invoices').attr("disabled", true);
    $('.download_invoices').attr("disabled", true);

    $("#datatable").on('change', 'input[type=checkbox]', function() {
        var selected = [];
        $.each($("input[name='hashed_ids[]']:checked"), function(){            
            selected.push($(this).val());
        });
        
    });

    $('#table_filter').on('keyup', function(){
        if (searchTimeout) {
            window.clearTimeout(searchTimeout);
        }
        searchTimeout = setTimeout(function() {
            filterTable();
        }, 500);
    });

    $('.select_all').change(function() {
        $(this).closest('table').find(':checkbox:not(:disabled)').prop('checked', this.checked);
    }); 

    $('.pay_invoices').click(function() {
        alert('pay');
    }); 

    $('.download_invoices').click(function() {
        alert('download');
    }); 

});  


function filterTable() {

    table_filter = $('#table_filter').val();
    data_table.ajax.reload();
}
</script>
@endsection

