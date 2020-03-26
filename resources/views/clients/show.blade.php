@extends('header')

@section('head')
    @parent

    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/select2.css') }}" rel="stylesheet" type="text/css"/>

@stop


@section('content')

    <div class="row">
        <div class="col-md-7">
            <ol class="breadcrumb">
              <li>{{ link_to('/clients', trans('texts.clients')) }}</li>
              <li class='active'>{{ $client->getDisplayName() }}</li> {!! $client->present()->statusLabel !!}
            </ol>
        </div>
        <div class="col-md-5">
            <div class="pull-right">
                {!! Former::open('clients/bulk')->autocomplete('off')->addClass('mainForm') !!}
                <div style="display:none">
                    {!! Former::text('action') !!}
                    {!! Former::text('public_id')->value($client->public_id) !!}
                </div>

                @if ($gatewayLink)
                    {!! Button::normal(trans('texts.view_in_gateway', ['gateway'=>$gatewayName]))
                            ->asLinkTo($gatewayLink)
                            ->withAttributes(['target' => '_blank']) !!}
                @endif

                @if ( ! $client->is_deleted)
                    @can('edit', $client)
                        {!! DropdownButton::normal(trans('texts.edit_client'))
                            ->withAttributes(['class'=>'normalDropDown'])
                            ->withContents([
                              ($client->trashed() ? false : ['label' => trans('texts.archive_client'), 'url' => "javascript:onArchiveClick()"]),
                              ['label' => trans('texts.delete_client'), 'url' => "javascript:onDeleteClick()"],
                              auth()->user()->is_admin ? \DropdownButton::DIVIDER : false,
                              auth()->user()->is_admin ? ['label' => trans('texts.purge_client'), 'url' => "javascript:onPurgeClick()"] : false,
                            ]
                          )->split() !!}
                    @endcan
                    @if ( ! $client->trashed())
                        @can('create', ENTITY_INVOICE)
                            {!! DropdownButton::primary(trans('texts.view_statement'))
                                    ->withAttributes(['class'=>'primaryDropDown'])
                                    ->withContents($actionLinks)->split() !!}
                        @endcan
                    @endif
                @endif

                @if ($client->trashed())
                    @can('edit', $client)
                        @if (auth()->user()->is_admin && $client->is_deleted)
                            {!! Button::danger(trans('texts.purge_client'))
                                    ->appendIcon(Icon::create('warning-sign'))
                                    ->withAttributes(['onclick' => 'onPurgeClick()']) !!}
                        @endif
                        {!! Button::primary(trans('texts.restore_client'))
                                ->appendIcon(Icon::create('cloud-download'))
                                ->withAttributes(['onclick' => 'onRestoreClick()']) !!}
                    @endcan
                @endif


              {!! Former::close() !!}

            </div>
        </div>
    </div>

	@if ($client->last_login > 0)
	<h3 style="margin-top:0px"><small>
		{{ trans('texts.last_logged_in') }} {{ Utils::timestampToDateTimeString(strtotime($client->last_login)) }}
	</small></h3>
	@endif

    <div class="panel panel-default">
    <div class="panel-body">
	<div class="row">

		<div class="col-md-3">
			<h3>{{ trans('texts.details') }}</h3>
            @if ($client->id_number)
                <p><i class="fa fa-id-number" style="width: 20px"></i>{{ trans('texts.id_number').': '.$client->id_number }}</p>
            @endif
            @if ($client->vat_number)
		  	   <p><i class="fa fa-vat-number" style="width: 20px"></i>{{ trans('texts.vat_number').': '.$client->vat_number }}</p>
            @endif

            @if ($client->account->customLabel('client1') && $client->custom_value1)
                {{ $client->account->present()->customLabel('client1') . ': ' }} {!! nl2br(e($client->custom_value1)) !!}<br/>
            @endif
            @if ($client->account->customLabel('client2') && $client->custom_value2)
                {{ $client->account->present()->customLabel('client2') . ': ' }} {!! nl2br(e($client->custom_value2)) !!}<br/>
            @endif

            @if ($client->work_phone)
                <i class="fa fa-phone" style="width: 20px"></i>{{ $client->work_phone }}
            @endif

            @if (floatval($client->task_rate))
                <p>{{ trans('texts.task_rate') }}: {{ Utils::roundSignificant($client->task_rate) }}</p>
            @endif

            <p/>

            @if ($client->public_notes)
                <p><i>{!! nl2br(e($client->public_notes)) !!}</i></p>
            @endif

            @if ($client->private_notes)
                <p><i>{!! nl2br(e($client->private_notes)) !!}</i></p>
            @endif

  	        @if ($client->industry || $client->size)
                @if ($client->industry)
                    {{ $client->industry->name }}
                @endif
                @if ($client->industry && $client->size)
                    |
                @endif
                @if ($client->size)
                    {{ $client->size->name }}<br/>
                @endif
            @endif

		  	@if ($client->website)
		  	   <p>{!! Utils::formatWebsite($client->website) !!}</p>
            @endif

            @if ($client->language)
                <p><i class="fa fa-language" style="width: 20px"></i>{{ $client->language->name }}</p>
            @endif

            <p>{{ $client->present()->paymentTerms }}</p>

            <div class="text-muted" style="padding-top:8px">
            @if ($client->show_tasks_in_portal)
                • {{ trans('texts.can_view_tasks') }}<br/>
            @endif
            @if ($client->account->hasReminders() && ! $client->send_reminders)
                • {{ trans('texts.is_not_sent_reminders') }}</br>
            @endif
            </div>
		</div>

        <div class="col-md-3">
			<h3>{{ trans('texts.address') }}</h3>

            @if ($client->addressesMatch())
                {!! $client->present()->address(ADDRESS_BILLING) !!}
            @else
                {!! $client->present()->address(ADDRESS_BILLING, true) !!}<br/>
                {!! $client->present()->address(ADDRESS_SHIPPING, true) !!}
            @endif

        </div>

		<div class="col-md-3">
			<h3>{{ trans('texts.contacts') }}</h3>
            @foreach ($client->contacts as $contact)
                @if ($contact->first_name || $contact->last_name)
                    <b>{{ $contact->first_name.' '.$contact->last_name }}</b><br/>
                @endif
                @if ($contact->email)
                    <i class="fa fa-envelope" style="width: 20px"></i>{!! HTML::mailto($contact->email, $contact->email) !!}<br/>
                @endif
                @if ($contact->phone)
                    <i class="fa fa-phone" style="width: 20px"></i>{{ $contact->phone }}<br/>
                @endif

                @if ($client->account->customLabel('contact1') && $contact->custom_value1)
                    {{ $client->account->present()->customLabel('contact1') . ': ' . $contact->custom_value1 }}<br/>
                @endif
                @if ($client->account->customLabel('contact2') && $contact->custom_value2)
                    {{ $client->account->present()->customLabel('contact2') . ': ' . $contact->custom_value2 }}<br/>
                @endif

                @if (Auth::user()->confirmed && $client->account->enable_client_portal)
                    <i class="fa fa-dashboard" style="width: 20px"></i><a href="{{ $contact->link }}"
                        onclick="window.open('{{ $contact->link }}?silent=true', '_blank');return false;">{{ trans('texts.view_in_portal') }}</a>                        
                    @if (config('services.postmark'))
                        <div style="padding-top:10px">
                            <a href="#" class="btn btn-sm btn-primary" onclick="showEmailHistory('{{ $contact->email }}')">
                                {{ trans('texts.email_history') }}
                            </a>
                        </div>
                    @endif
                    <br/>
                @endif
                <br/>
            @endforeach
		</div>

		<div class="col-md-3">
			<h3>{{ trans('texts.standing') }}
			<table class="table" style="width:100%">
				<tr>
					<td><small>{{ trans('texts.paid_to_date') }}</small></td>
					<td style="text-align: right">{{ Utils::formatMoney($client->paid_to_date, $client->getCurrencyId()) }}</td>
				</tr>
				<tr>
					<td><small>{{ trans('texts.balance') }}</small></td>
					<td style="text-align: right">{{ Utils::formatMoney($client->balance, $client->getCurrencyId()) }}</td>
				</tr>
				@if ($credit > 0)
				<tr>
					<td><small>{{ trans('texts.credit') }}</small></td>
					<td style="text-align: right">{{ Utils::formatMoney($credit, $client->getCurrencyId()) }}</td>
				</tr>
				@endif
			</table>
			</h3>
		</div>
	</div>
    </div>
    </div>

    @if ($client->showMap())

        <iframe
          width="100%"
          height="200px"
          frameborder="0" style="border:0"
          src="https://www.google.com/maps/embed/v1/place?key={{ env('GOOGLE_MAPS_API_KEY') }}&q={!! e("{$client->address1} {$client->address2} {$client->city} {$client->state} {$client->postal_code} " . ($client->country ? $client->country->getName() : '')) !!}" allowfullscreen>
        </iframe>

    @endif

	<ul class="nav nav-tabs nav-justified">
		{!! Form::tab_link('#activity', trans('texts.activity'), true) !!}
        @if ($hasTasks)
            {!! Form::tab_link('#tasks', trans('texts.tasks')) !!}
        @endif
        @if ($hasExpenses)
            {!! Form::tab_link('#expenses', trans('texts.expenses')) !!}
        @endif
		@if ($hasQuotes)
			{!! Form::tab_link('#quotes', trans('texts.quotes')) !!}
		@endif
        @if ($hasRecurringInvoices)
            {!! Form::tab_link('#recurring_invoices', trans('texts.recurring')) !!}
        @endif
		{!! Form::tab_link('#invoices', trans('texts.invoices')) !!}
		{!! Form::tab_link('#payments', trans('texts.payments')) !!}
        @if ($account->isModuleEnabled(ENTITY_CREDIT))
            {!! Form::tab_link('#credits', trans('texts.credits')) !!}
        @endif
	</ul><br/>

	<div class="tab-content">

        <div class="tab-pane active" id="activity">
			{!! Datatable::table()
		    	->addColumn(
		    		trans('texts.date'),
		    		trans('texts.message'),
		    		trans('texts.balance'),
		    		trans('texts.adjustment'))
		    	->setUrl(url('api/activities/'. $client->public_id))
                ->setCustomValues('entityType', 'activity')
                ->setCustomValues('clientId', $client->public_id)
                ->setCustomValues('rightAlign', [2, 3])
		    	->setOptions('sPaginationType', 'bootstrap')
		    	->setOptions('bFilter', false)
		    	->setOptions('aaSorting', [['0', 'desc']])
		    	->render('datatable') !!}
        </div>

    @if ($hasTasks)
        <div class="tab-pane" id="tasks">
            @include('list', [
                'entityType' => ENTITY_TASK,
                'datatable' => new \App\Ninja\Datatables\TaskDatatable(true, true),
                'clientId' => $client->public_id,
                'url' => url('api/tasks/' . $client->public_id),
            ])
        </div>
    @endif

    @if ($hasExpenses)
        <div class="tab-pane" id="expenses">
            @include('list', [
                'entityType' => ENTITY_EXPENSE,
                'datatable' => new \App\Ninja\Datatables\ExpenseDatatable(true, true),
                'clientId' => $client->public_id,
                'url' => url('api/client_expenses/' . $client->public_id),
            ])
        </div>
    @endif

    @if (Utils::hasFeature(FEATURE_QUOTES) && $hasQuotes)
        <div class="tab-pane" id="quotes">
            @include('list', [
                'entityType' => ENTITY_QUOTE,
                'datatable' => new \App\Ninja\Datatables\InvoiceDatatable(true, true, ENTITY_QUOTE),
                'clientId' => $client->public_id,
                'url' => url('api/quotes/' . $client->public_id),
            ])
        </div>
    @endif

    @if ($hasRecurringInvoices)
        <div class="tab-pane" id="recurring_invoices">
            @include('list', [
                'entityType' => ENTITY_RECURRING_INVOICE,
                'datatable' => new \App\Ninja\Datatables\RecurringInvoiceDatatable(true, true),
                'clientId' => $client->public_id,
                'url' => url('api/recurring_invoices/' . $client->public_id),
            ])
        </div>
    @endif

		<div class="tab-pane" id="invoices">
            @include('list', [
                'entityType' => ENTITY_INVOICE,
                'datatable' => new \App\Ninja\Datatables\InvoiceDatatable(true, true),
                'clientId' => $client->public_id,
                'url' => url('api/invoices/' . $client->public_id),
            ])
        </div>

        <div class="tab-pane" id="payments">
            @include('list', [
                'entityType' => ENTITY_PAYMENT,
                'datatable' => new \App\Ninja\Datatables\PaymentDatatable(true, true),
                'clientId' => $client->public_id,
                'url' => url('api/payments/' . $client->public_id),
            ])
        </div>

    @if ($account->isModuleEnabled(ENTITY_CREDIT))
        <div class="tab-pane" id="credits">
            @include('list', [
                'entityType' => ENTITY_CREDIT,
                'datatable' => new \App\Ninja\Datatables\CreditDatatable(true, true),
                'clientId' => $client->public_id,
                'url' => url('api/credits/' . $client->public_id),
            ])
        </div>
    @endif

    </div>

    <div class="modal fade" id="emailHistoryModal" tabindex="-1" role="dialog" aria-labelledby="emailHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="myModalLabel">{{ trans('texts.email_history') }}</h4>
                </div>

                <div class="container" style="width: 100%; padding-bottom: 0px !important">
                <div class="panel panel-default">
                <div class="panel-body">

                </div>
                </div>
                </div>

                <div class="modal-footer" id="signUpFooter" style="margin-top: 0px">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('texts.close') }} </button>
                    <button type="button" class="btn btn-danger" onclick="onReactivateClick()" id="reactivateButton" style="display:none;">{{ trans('texts.reactivate') }} </button>
                </div>
            </div>
        </div>
    </div>


	<script type="text/javascript">

    var loadedTabs = {};

	$(function() {
		$('.normalDropDown:not(.dropdown-toggle)').click(function(event) {
            openUrlOnClick('{{ URL::to('clients/' . $client->public_id . '/edit') }}', event);
		});
		$('.primaryDropDown:not(.dropdown-toggle)').click(function(event) {
            openUrlOnClick('{{ URL::to('clients/statement/' . $client->public_id ) }}', event);
		});

        // load datatable data when tab is shown and remember last tab selected
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
          var target = $(e.target).attr("href") // activated tab
          target = target.substring(1);
          if (isStorageSupported()) {
              localStorage.setItem('client_tab', target);
          }
          if (!loadedTabs.hasOwnProperty(target) && window['load_' + target]) {
            loadedTabs[target] = true;
            window['load_' + target]();
          }
        });

        var tab = window.location.hash || (localStorage.getItem('client_tab') || '');
        tab = tab.replace('#', '');
        var selector = '.nav-tabs a[href="#' + tab + '"]';

        if (tab && tab != 'activity' && $(selector).length && window['load_' + tab]) {
            $(selector).tab('show');
        } else {
            window['load_activity']();
        }
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
		sweetConfirm(function() {
			$('#action').val('delete');
			$('.mainForm').submit();
		});
	}

    function onPurgeClick() {
		sweetConfirm(function() {
			$('#action').val('purge');
			$('.mainForm').submit();
		}, "{{ trans('texts.purge_client_warning') . "\\n\\n" . trans('texts.mobile_refresh_warning') . "\\n\\n" . trans('texts.no_undo') }}");
	}

    function showEmailHistory(email) {
        window.emailBounceId = false;
        $('#emailHistoryModal .panel-body').html("{{ trans('texts.loading') }}...");
        $('#reactivateButton').hide();
        $('#emailHistoryModal').modal('show');
        $.post('{{ url('/email_history') }}', {email: email}, function(data) {
            $('#emailHistoryModal .panel-body').html(data.str);
            window.emailBounceId = data.bounce_id;
            $('#reactivateButton').toggle(!! window.emailBounceId);
        })
    }

    function onReactivateClick() {
        $.post('{{ url('/reactivate_email') }}/' + window.emailBounceId, function(data) {
            $('#emailHistoryModal').modal('hide');
            swal("{{ trans('texts.reactivated_email') }}")
        })
    }

	</script>

@stop
