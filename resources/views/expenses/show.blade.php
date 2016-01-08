@extends('header')

@section('head')
    @parent
@stop

@section('content')
	<div class="pull-right">
		{!! Former::open('expenses/bulk')->addClass('mainForm') !!}
		<div style="display:none">
			{!! Former::text('action') !!}
			{!! Former::text('public_id')->value($expense->public_id) !!}
		</div>

		@if ($expense->trashed())
			{!! Button::primary(trans('texts.restore_expense'))->withAttributes(['onclick' => 'onRestoreClick()']) !!}
		@else
		    {!! DropdownButton::normal(trans('texts.edit_expense'))
                ->withAttributes(['class'=>'normalDropDown'])
                ->withContents([
			      ['label' => trans('texts.archive_expense'), 'url' => "javascript:onArchiveClick()"],
			      ['label' => trans('texts.delete_expense'), 'url' => "javascript:onDeleteClick()"],
			    ]
			  )->split() !!}

			{!! DropdownButton::primary(trans('texts.new_expense'))
                    ->withAttributes(['class'=>'primaryDropDown'])
                    ->withContents($actionLinks)->split() !!}
		@endif
	  {!! Former::close() !!}

	</div>

	<h2>{{ trans('texts.view_expense_num', ['expense' => $expense->public_id]) }}</h2>
    <div class="panel panel-default">
    <div class="panel-body">
	<div class="row">

		<div class="col-md-3">
			<h3>{{ trans('texts.details') }}</h3>

		  	<p>{{ $expense->public_notes }}</p>
		</div>

		<div class="col-md-4">
			<h3>{{ trans('texts.standing') }}
			<table class="table" style="width:100%">
				<tr>
					<td><small>{{ trans('texts.expense_date') }}</small></td>
					<td style="text-align: right">{{ Utils::fromSqlDate($expense->expense_date) }}</td>
				</tr>
				<tr>
					<td><small>{{ trans('texts.expense_amount') }}</small></td>
					<td style="text-align: right">{{ Utils::formatMoney($expense->amount) }}</td>
				</tr>
				@if ($credit > 0)
				<tr>
					<td><small>{{ trans('texts.expense_amount_cur') }}</small></td>
					<td style="text-align: right">{{ Utils::formatMoney($$expense->amount_cur, $expense->curency_id) }}</td>
				</tr>
				@endif
			</table>
			</h3>
		</div>
	</div>
    </div>
    </div>

	<ul class="nav nav-tabs nav-justified">
		{!! HTML::tab_link('#activity', trans('texts.activity'), true) !!}
	</ul>

	<div class="tab-content">
        <div class="tab-pane active" id="activity">
			{!! Datatable::table()
		    	->addColumn(
		    		trans('texts.date'),
		    		trans('texts.message'),
		    		trans('texts.balance'),
		    		trans('texts.adjustment'))
		    	->setUrl(url('api/expenseactivities/'. $expense->public_id))
                ->setCustomValues('entityType', 'activity')
		    	->setOptions('sPaginationType', 'bootstrap')
		    	->setOptions('bFilter', false)
		    	->setOptions('aaSorting', [['0', 'desc']])
		    	->render('datatable') !!}
        </div>
    </div>

	<script type="text/javascript">

    var loadedTabs = {};

	$(function() {
		$('.normalDropDown:not(.dropdown-toggle)').click(function() {
			window.location = '{{ URL::to('expenses/' . $expense->public_id . '/edit') }}';
		});
		$('.primaryDropDown:not(.dropdown-toggle)').click(function() {
			window.location = '{{ URL::to('expenses/create/' . $expense->public_id ) }}';
		});
	});

	function onArchiveClick() {
		$('#action').val('archive');
		$('.mainForm').submit();
	}

	function onRestoreClick() {
		$('#action').val('restore');
		$('.mainForm').submit();
	}

	function onDeleteClick() {
		if (confirm("{!! trans('texts.are_you_sure') !!}")) {
			$('#action').val('delete');
			$('.mainForm').submit();
		}
	}

	</script>

@stop
