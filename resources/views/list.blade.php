@extends('header')

@section('head')
	@parent

    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/select2.css') }}" rel="stylesheet" type="text/css"/>

	<style type="text/css">
		.select2-selection {
			border: 1px solid #dfe0e1 !important;
			border-radius: 2px;
			padding: 2px;
		}
	</style>

@stop

@section('content')

	{!! Former::open(Utils::pluralizeEntityType($entityType) . '/bulk')->addClass('listForm') !!}

	<div style="display:none">
		{!! Former::text('action') !!}
        {!! Former::text('public_id') !!}
        {!! Former::text('datatable')->value('true') !!}
	</div>

	@can('create', 'invoice')
		@if ($entityType == ENTITY_TASK)
			{!! Button::primary(trans('texts.invoice'))->withAttributes(['class'=>'invoice', 'onclick' =>'submitForm("invoice")'])->appendIcon(Icon::create('check')) !!}
		@endif
		@if ($entityType == ENTITY_EXPENSE)
			{!! Button::primary(trans('texts.invoice'))->withAttributes(['class'=>'invoice', 'onclick' =>'submitForm("invoice")'])->appendIcon(Icon::create('check')) !!}
		@endif
	@endcan

    @if (in_array($entityType, [ENTITY_EXPENSE_CATEGORY, ENTITY_PRODUCT]))
        {!! Button::normal(trans('texts.archive'))->asLinkTo('javascript:submitForm("archive")')->appendIcon(Icon::create('trash')) !!}
    @else
    	{!! DropdownButton::normal(trans('texts.archive'))->withContents([
    		      ['label' => trans('texts.archive_'.$entityType), 'url' => 'javascript:submitForm("archive")'],
    		      ['label' => trans('texts.delete_'.$entityType), 'url' => 'javascript:submitForm("delete")'],
    		    ])->withAttributes(['class'=>'archive'])->split() !!}
    @endif

	&nbsp;
	<label for="trashed" style="font-weight:normal; margin-left: 10px;">
		<!--
		<input id="trashed" type="checkbox" onclick="setTrashVisible()"
		{{ Session::get("show_trash:{$entityType}") ? 'checked' : ''}}/>&nbsp; {{ trans('texts.show_archived_deleted')}}
		-->
		{!! Former::multiselect('statuses')
				->select('Active')
				->style('width: 200px')
				->options($statuses)
				->raw() !!}
	</label>

	<div id="top_right_buttons" class="pull-right">
		<input id="tableFilter" type="text" style="width:140px;margin-right:17px;background-color: white !important"
            class="form-control pull-left" placeholder="{{ trans('texts.filter') }}" value="{{ Input::get('filter') }}"/>
        @if ($entityType == ENTITY_EXPENSE)
            {!! Button::normal(trans('texts.categories'))->asLinkTo(URL::to('/expense_categories'))->appendIcon(Icon::create('list')) !!}
        @endif

		@if (Auth::user()->can('create', $entityType))
        	{!! Button::primary(trans("texts.new_{$entityType}"))->asLinkTo(url(Utils::pluralizeEntityType($entityType) . '/create'))->appendIcon(Icon::create('plus-sign')) !!}
		@endif

	</div>

	{!! Datatable::table()
    	->addColumn($columns)
    	->setUrl(route('api.' . Utils::pluralizeEntityType($entityType)))
        ->setCustomValues('rightAlign', isset($rightAlign) ? $rightAlign : [])
    	->setOptions('sPaginationType', 'bootstrap')
        ->setOptions('aaSorting', [[isset($sortCol) ? $sortCol : '1', 'desc']])
    	->render('datatable') !!}

	@if ($entityType == ENTITY_PAYMENT)
		<div class="modal fade" id="paymentRefundModal" tabindex="-1" role="dialog" aria-labelledby="paymentRefundModalLabel" aria-hidden="true">
		  <div class="modal-dialog" style="min-width:150px">
			<div class="modal-content">
			  <div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
				<h4 class="modal-title" id="paymentRefundModalLabel">{{ trans('texts.refund_payment') }}</h4>
			  </div>

				<div class="modal-body">
					<div class="form-horizontal">
					  <div class="form-group">
						<label for="refundAmount" class="col-sm-offset-2 col-sm-2 control-label">{{ trans('texts.amount') }}</label>
						<div class="col-sm-4">
							<div class="input-group">
  								<span class="input-group-addon" id="refundCurrencySymbol"></span>
						  		<input type="number" class="form-control" id="refundAmount" name="amount" step="0.01" min="0.01" placeholder="{{ trans('texts.amount') }}">
							</div>
							<div class="help-block">{{ trans('texts.refund_max') }} <span id="refundMax"></span></div>
						</div>
					  </div>
					</div>
				</div>

			 <div class="modal-footer" style="margin-top: 0px">
				<button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.cancel') }}</button>
				<button type="button" class="btn btn-primary" id="completeRefundButton">{{ trans('texts.refund') }}</button>
			 </div>

			</div>
		  </div>
		</div>
	@endif

    {!! Former::close() !!}

    <script type="text/javascript">

	function submitForm(action) {
		if (action == 'delete') {
            sweetConfirm(function() {
                $('#action').val(action);
        		$('form.listForm').submit();
            });
		} else {
    		$('#action').val(action);
    		$('form.listForm').submit();
        }
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

	@if ($entityType == ENTITY_PAYMENT)
		var paymentId = null;
		function showRefundModal(id, amount, formatted, symbol){
			paymentId = id;
			$('#refundCurrencySymbol').text(symbol);
			$('#refundMax').text(formatted);
			$('#refundAmount').val(amount).attr('max', amount);
			$('#paymentRefundModal').modal('show');
		}

		function handleRefundClicked(){
			$('#public_id').val(paymentId);
			submitForm('refund');
		}
	@endif

	/*
	function setTrashVisible() {
		var checked = $('#trashed').is(':checked');
		var url = '{{ URL::to('view_archive/' . $entityType) }}' + (checked ? '/true' : '/false');

        $.get(url, function(data) {
            refreshDatatable();
        })
	}
	*/

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

		@if ($entityType == ENTITY_PAYMENT)
		$('#completeRefundButton').click(handleRefundClicked)
		@endif

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

		$('#statuses').select2({
			placeholder: "{{ trans('texts.status') }}",
		}).on('change', function() {
			refreshDatatable();
		}).val([0]).trigger('change');


		//$('#statuses').select2().val([0,1,2]).trigger('change');
    });

    </script>

@stop
