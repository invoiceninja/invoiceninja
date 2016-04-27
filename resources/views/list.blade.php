@extends('header')

@section('content')

	{!! Former::open($entityType . 's/bulk')->addClass('listForm') !!}

	<div style="display:none">
		{!! Former::text('action') !!}
        {!! Former::text('public_id') !!}
	</div>

	@can('create', 'invoice')
		@if ($entityType == ENTITY_TASK)
			{!! Button::primary(trans('texts.invoice'))->withAttributes(['class'=>'invoice', 'onclick' =>'submitForm("invoice")'])->appendIcon(Icon::create('check')) !!}
		@endif
		@if ($entityType == ENTITY_EXPENSE)
			{!! Button::primary(trans('texts.invoice'))->withAttributes(['class'=>'invoice', 'onclick' =>'submitForm("invoice")'])->appendIcon(Icon::create('check')) !!}
		@endif
	@endcan

	{!! DropdownButton::normal(trans('texts.archive'))->withContents([
		      ['label' => trans('texts.archive_'.$entityType), 'url' => 'javascript:submitForm("archive")'],
		      ['label' => trans('texts.delete_'.$entityType), 'url' => 'javascript:submitForm("delete")'],
		    ])->withAttributes(['class'=>'archive'])->split() !!}
	
	&nbsp;<label for="trashed" style="font-weight:normal; margin-left: 10px;">
		<input id="trashed" type="checkbox" onclick="setTrashVisible()" 
			{{ Session::get("show_trash:{$entityType}") ? 'checked' : ''}}/>&nbsp; {{ trans('texts.show_archived_deleted')}} {{ Utils::transFlowText($entityType.'s') }}
	</label>

	<div id="top_right_buttons" class="pull-right">
		<input id="tableFilter" type="text" style="width:140px;margin-right:17px;background-color: white !important" 
            class="form-control pull-left" placeholder="{{ trans('texts.filter') }}" value="{{ Input::get('filter') }}"/>
        @if (Auth::user()->hasFeature(FEATURE_QUOTES) && $entityType == ENTITY_INVOICE)
            {!! Button::normal(trans('texts.quotes'))->asLinkTo(URL::to('/quotes'))->appendIcon(Icon::create('list')) !!}
            {!! Button::normal(trans('texts.recurring'))->asLinkTo(URL::to('/recurring_invoices'))->appendIcon(Icon::create('list')) !!}
        @elseif ($entityType == ENTITY_EXPENSE)
            {!! Button::normal(trans('texts.vendors'))->asLinkTo(URL::to('/vendors'))->appendIcon(Icon::create('list')) !!}
        @elseif ($entityType == ENTITY_CLIENT)
            {!! Button::normal(trans('texts.credits'))->asLinkTo(URL::to('/credits'))->appendIcon(Icon::create('list')) !!}
        @endif

		@if (Auth::user()->hasPermission('create_all'))
        	{!! Button::primary(trans("texts.new_$entityType"))->asLinkTo(URL::to("/{$entityType}s/create"))->appendIcon(Icon::create('plus-sign')) !!}
		@endif
        
	</div>

	{!! Datatable::table()		
    	->addColumn($columns)
    	->setUrl(route('api.' . $entityType . 's'))    	
        ->setCustomValues('rightAlign', isset($rightAlign) ? $rightAlign : [])
    	->setOptions('sPaginationType', 'bootstrap')
        ->setOptions('aaSorting', [[isset($sortCol) ? $sortCol : '1', 'desc']])
    	->render('datatable') !!}
    
    {!! Former::close() !!}

    <script type="text/javascript">

	function submitForm(action) {
		if (action == 'delete') {
            if (!confirm('{!! trans("texts.are_you_sure") !!}')) {			
				return;
			}
		}		

		$('#action').val(action);
		$('form.listForm').submit();		
	}

	function deleteEntity(id) {
		$('#public_id').val(id);
		submitForm('delete');
	}

	function archiveEntity(id) {
		$('#public_id').val(id);
		submitForm('archive');
	}

    function restoreEntity(id) {
        $('#public_id').val(id);
        submitForm('restore');
    }
    function convertEntity(id) {
        $('#public_id').val(id);
        submitForm('convert');
    }

	function markEntity(id) {
		$('#public_id').val(id);
		submitForm('markSent');
	}

    function stopTask(id) {
        $('#public_id').val(id);
        submitForm('stop');
    }

    function invoiceEntity(id) {
        $('#public_id').val(id);
        submitForm('invoice');
    }

	function setTrashVisible() {
		var checked = $('#trashed').is(':checked');
		var url = '{{ URL::to('view_archive/' . $entityType) }}' + (checked ? '/true' : '/false');

        $.get(url, function(data) {
            refreshDatatable();
        })
	}

    $(function() {
        var tableFilter = '';
        var searchTimeout = false;

        var oTable0 = $('#DataTables_Table_0').dataTable();
        var oTable1 = $('#DataTables_Table_1').dataTable(); 
        function filterTable(val) { 
            if (val == tableFilter) {
                return;
            }
            tableFilter = val;
            oTable0.fnFilter(val);
        }

        $('#tableFilter').on('keyup', function(){
            if (searchTimeout) {
                window.clearTimeout(searchTimeout);
            }

            searchTimeout = setTimeout(function() {
                filterTable($('#tableFilter').val());
            }, 500);
        })

        if ($('#tableFilter').val()) {
            filterTable($('#tableFilter').val());
        }

        window.onDatatableReady = function() {      
            $(':checkbox').click(function() {
                setBulkActionsEnabled();
            }); 

            $('tbody tr').unbind('click').click(function(event) {
                if (event.target.type !== 'checkbox' && event.target.type !== 'button' && event.target.tagName.toLowerCase() !== 'a') {
                    $checkbox = $(this).closest('tr').find(':checkbox:not(:disabled)');
                    var checked = $checkbox.prop('checked');
                    $checkbox.prop('checked', !checked);
                    setBulkActionsEnabled();
                }
            });

            actionListHandler();
        }

        $('.archive, .invoice').prop('disabled', true);
        $('.archive:not(.dropdown-toggle)').click(function() {
            submitForm('archive');
        });

        $('.selectAll').click(function() {
            $(this).closest('table').find(':checkbox:not(:disabled)').prop('checked', this.checked);
        });

        function setBulkActionsEnabled() {
            var buttonLabel = "{{ trans('texts.archive') }}";
            var count = $('tbody :checkbox:checked').length;
            $('button.archive, button.invoice').prop('disabled', !count); 
            if (count) {
                buttonLabel += ' (' + count + ')';
            }
            $('button.archive').not('.dropdown-toggle').text(buttonLabel);
        }

    });

    </script>

@stop