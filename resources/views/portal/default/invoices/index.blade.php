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
        @include('portal.default.flash-message')

			<div class="col-lg-12" style="padding-bottom: 10px;">
				
                <div class="pull-left">

                    <div class="btn-group">
                      <button type="button" class="btn btn-success" id="pay_invoices">{{ ctrans('texts.pay_now') }}</button>
                      <button type="button" class="btn btn-success dropdown-toggle dropdown-toggle-split" id="pay_invoices_drop" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="sr-only">Toggle Dropdown</span>
                      </button>
                      <div class="dropdown-menu">
                        <a class="dropdown-item" id="download_invoices">{{ctrans('texts.download_pdf')}}</a>
                      </div>
                    </div>


                    <select class="form-control" style="width: 220px;" id="statuses" name="client_status[]" multiple="multiple">
                          <option value="paid">{{ ctrans('texts.status_paid') }}</option>
                          <option value="unpaid">{{ ctrans('texts.status_unpaid') }}</option>
                          <option value="overdue">{{ ctrans('texts.overdue') }}</option>
                    </select>

                </div>

				<!-- Filters / Buttons in here.-->
				<div id="top_right_buttons" class="pull-right">

					<input id="table_filter" type="text" style="width:180px;background-color: white !important"
				        class="form-control pull-left" placeholder="{{ trans('texts.filter')}}" value=""/>
				</div>
            </div>

		</div>

        <div class="row">
            <div class="animated fadeIn col-lg-12 card mt-10">

            {!! $html->table(['class' => 'table table-hover table-striped', 'id' => 'datatable'], true) !!}

            </div>
        </div>

    </div>
</main>
</body>
@endsection
@push('scripts')
	<script src="//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js" type="text/javascript"></script>
    <script src="//cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js"></script>
    <script src="/vendors/js/select2.min.js"></script>
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
	        } 

        },
        drawCallback: function(settings){

           data = this.api().ajax.json().data;

        },
        columns: [

            {data: 'checkbox', name: 'checkbox', title: '<input type="checkbox" class="select_all" >', searchable: false, orderable: false},
            {data: 'number', name: 'number', title: '{{ctrans('texts.invoice_number')}}', visible: true},
            {data: 'date', name: 'date', title: '{{ctrans('texts.invoice_date')}}', visible: true},
            {data: 'amount', name: 'amount', title: '{{ctrans('texts.total')}}', visible: true},
            {data: 'balance', name: 'balance', title: '{{ctrans('texts.balance')}}', visible: true},
            {data: 'due_date', name: 'due_date', title: '{{ctrans('texts.due_date')}}', visible: true},
            {data: 'status_id', name: 'status_id', title: '{{ctrans('texts.status')}}', visible: true},
            {data: 'action', name: 'action', title: '', searchable: false, orderable: false, width: '10%'},
        ]
    });
});

</script>

<script>
    var searchTimeout = false;
    var selected = [];   

$(document).ready(function() {

    toggleButtonGroup();


    $('#datatable').on('click', '.odd, .even' ,function(e) {

        if($(e.target).is(':checkbox')) return; //ignore when click on the checkbox
        
        var $cb= $(this).find('input[type="checkbox"]');
        $cb.prop('checked', !$cb.is(':checked'));

        buildCheckboxArray();
    });

    $("#datatable").on('change', 'input[type=checkbox]', function() {
        buildCheckboxArray();
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

    $('#pay_invoices').click(function() {

        $('#pay_invoices').addClass('disabled');
        $('#pay_invoices_drop').addClass('disabled');
        $('#download_invoices').addClass('disabled');
        

        $('#hashed_ids').val(selected);
        $('#action').val('payment');

        $('#payment_form').submit();
    }); 

    $('#download_invoices').click(function() {
        $('#hashed_ids').val(selected);
        $('#action').val('download');

        $('#payment_form').submit();
    }); 

 
}); 

function buildCheckboxArray()
{
    selected = []; 
    $.each($("input[name='hashed_ids[]']:checked"), function(){  
                 
        selected.push($(this).val());
    });
    
    toggleButtonGroup();
}

function toggleButtonGroup()
{
    if(selected.length == 0)
    {
        $('#pay_invoices').addClass('disabled');
        $('#pay_invoices_drop').addClass('disabled');
        $('#download_invoices').addClass('disabled');

    }
    else 
    {
        $('#pay_invoices').removeClass('disabled');
        $('#pay_invoices_drop').removeClass('disabled');
        $('#download_invoices').removeClass('disabled');
    }
}

function filterTable() {

    table_filter = $('#table_filter').val();
    data_table.ajax.reload();
}

function payInvoice(hashed_id) {

    $('#pay_invoices_drop').addClass('disabled');
    $('#download_invoices').addClass('disabled');
    
    $('#hashed_ids').val(hashed_id);
    $('#action').val('payment');

    $('#payment_form').submit();

}

// Setup status filter
$('#statuses').select2({
    placeholder: "{{ ctrans('texts.status') }}",
    //allowClear: true,
    templateSelection: function(data, container) {
        if (data.id == 'paid') {
            $(container).css('color', '#fff');
            $(container).css('background-color', '#00c979');
            $(container).css('border-color', '#00a161');
        } else if (data.id == 'unpaid') {
            $(container).css('color', '#fff');
            $(container).css('background-color', '#f0ad4e');
            $(container).css('border-color', '#eea236');
        } else if (data.id == 'overdue') {
            $(container).css('color', '#fff');
            $(container).css('background-color', '#d9534f');
            $(container).css('border-color', '#d43f3a');            
        }
        return data.text;
    }
}).on('change', function() {
    
    client_statuses = $('#statuses').val();

    if (client_statuses) {
        client_statuses = client_statuses.join(',');
    } else {
        client_statuses = '';
    }

    data_table.ajax.reload();

});
</script>
@endpush

