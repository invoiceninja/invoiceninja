@extends('header')

@section('head')
    @parent

    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/select2.css') }}" rel="stylesheet" type="text/css"/>

    @if ($client->showMap())
        <style>
          #map {
            width: 100%;
            height: 200px;
            border-width: 1px;
            border-style: solid;
            border-color: #ddd;
          }
        </style>

        <script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}"></script>
    @endif
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
                {!! Former::open('clients/bulk')->addClass('mainForm') !!}
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

            @if ($client->address1)
                {{ $client->address1 }}<br/>
            @endif
            @if ($client->address2)
                {{ $client->address2 }}<br/>
            @endif
            @if ($client->getCityState())
                {{ $client->getCityState() }}<br/>
            @endif
            @if ($client->country)
                {{ $client->country->name }}<br/>
            @endif

            @if ($client->account->custom_client_label1 && $client->custom_value1)
                {{ $client->account->custom_client_label1 . ': ' . $client->custom_value1 }}<br/>
            @endif
            @if ($client->account->custom_client_label2 && $client->custom_value2)
                {{ $client->account->custom_client_label2 . ': ' . $client->custom_value2 }}<br/>
            @endif

            @if ($client->work_phone)
                <i class="fa fa-phone" style="width: 20px"></i>{{ $client->work_phone }}
            @endif

            @if ($client->public_notes)
                <p><i>{{ $client->public_notes }}</i></p>
            @endif

            @if ($client->private_notes)
                <p><i>{{ $client->private_notes }}</i></p>
            @endif

  	        @if ($client->client_industry)
                {{ $client->client_industry->name }}<br/>
            @endif
            @if ($client->client_size)
                {{ $client->client_size->name }}<br/>
            @endif

		  	@if ($client->website)
		  	   <p>{!! Utils::formatWebsite($client->website) !!}</p>
            @endif

            @if ($client->language)
                <p><i class="fa fa-language" style="width: 20px"></i>{{ $client->language->name }}</p>
            @endif

            <p>{{ $client->present()->paymentTerms }}</p>
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

                @if ($client->account->custom_contact_label1 && $contact->custom_value1)
                    {{ $client->account->custom_contact_label1 . ': ' . $contact->custom_value1 }}<br/>
                @endif
                @if ($client->account->custom_contact_label2 && $contact->custom_value2)
                    {{ $client->account->custom_contact_label2 . ': ' . $contact->custom_value2 }}<br/>
                @endif

                @if (Auth::user()->confirmed && $client->account->enable_client_portal)
                    <i class="fa fa-dashboard" style="width: 20px"></i><a href="{{ $contact->link }}"
                        onclick="window.open('{{ $contact->link }}?silent=true', '_blank');return false;">{{ trans('texts.view_client_portal') }}
                    </a><br/>
                @endif
                <br/>
		  	@endforeach
		</div>

		<div class="col-md-4">
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
        <div id="map"></div>
        <br/>
    @endif

	<ul class="nav nav-tabs nav-justified">
		{!! Form::tab_link('#activity', trans('texts.activity'), true) !!}
        @if ($hasTasks && Utils::isPro())
            {!! Form::tab_link('#tasks', trans('texts.tasks')) !!}
        @endif
		@if ($hasQuotes && Utils::isPro())
			{!! Form::tab_link('#quotes', trans('texts.quotes')) !!}
		@endif
        @if ($hasRecurringInvoices)
            {!! Form::tab_link('#recurring_invoices', trans('texts.recurring')) !!}
        @endif
		{!! Form::tab_link('#invoices', trans('texts.invoices')) !!}
		{!! Form::tab_link('#payments', trans('texts.payments')) !!}
		{!! Form::tab_link('#credits', trans('texts.credits')) !!}
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
            ])
        </div>
    @endif


    @if (Utils::hasFeature(FEATURE_QUOTES) && $hasQuotes)
        <div class="tab-pane" id="quotes">
            @include('list', [
                'entityType' => ENTITY_QUOTE,
                'datatable' => new \App\Ninja\Datatables\InvoiceDatatable(true, true, ENTITY_QUOTE),
                'clientId' => $client->public_id,
            ])
        </div>
    @endif

    @if ($hasRecurringInvoices)
        <div class="tab-pane" id="recurring_invoices">
            @include('list', [
                'entityType' => ENTITY_RECURRING_INVOICE,
                'datatable' => new \App\Ninja\Datatables\RecurringInvoiceDatatable(true, true),
                'clientId' => $client->public_id,
            ])
        </div>
    @endif

		<div class="tab-pane" id="invoices">
            @include('list', [
                'entityType' => ENTITY_INVOICE,
                'datatable' => new \App\Ninja\Datatables\InvoiceDatatable(true, true),
                'clientId' => $client->public_id,
            ])
        </div>

        <div class="tab-pane" id="payments">
            @include('list', [
                'entityType' => ENTITY_PAYMENT,
                'datatable' => new \App\Ninja\Datatables\PaymentDatatable(true, true),
                'clientId' => $client->public_id,
            ])
        </div>

        <div class="tab-pane" id="credits">
            @include('list', [
                'entityType' => ENTITY_CREDIT,
                'datatable' => new \App\Ninja\Datatables\CreditDatatable(true, true),
                'clientId' => $client->public_id,
            ])
        </div>

    </div>

	<script type="text/javascript">

    var loadedTabs = {};

	$(function() {
		$('.normalDropDown:not(.dropdown-toggle)').click(function() {
			window.location = '{{ URL::to('clients/' . $client->public_id . '/edit') }}';
		});
		$('.primaryDropDown:not(.dropdown-toggle)').click(function() {
			window.location = '{{ URL::to('clients/statement/' . $client->public_id ) }}';
		});

        // load datatable data when tab is shown and remember last tab selected
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
          var target = $(e.target).attr("href") // activated tab
          target = target.substring(1);
          if (isStorageSupported()) {
              localStorage.setItem('client_tab', target);
          }
          if (!loadedTabs.hasOwnProperty(target)) {
            loadedTabs[target] = true;
            window['load_' + target]();
          }
        });

        var tab = window.location.hash || (localStorage.getItem('client_tab') || '');
        tab = tab.replace('#', '');
        var selector = '.nav-tabs a[href="#' + tab + '"]';
        if (tab && tab != 'activity' && $(selector).length) {
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

    @if ($client->showMap())
        function initialize() {
            var mapCanvas = document.getElementById('map');
            var mapOptions = {
                zoom: {{ DEFAULT_MAP_ZOOM }},
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                zoomControl: true,
            };

            var map = new google.maps.Map(mapCanvas, mapOptions)
            var address = "{{ "{$client->address1} {$client->address2} {$client->city} {$client->state} {$client->postal_code} " . ($client->country ? $client->country->name : '') }}";

            geocoder = new google.maps.Geocoder();
            geocoder.geocode( { 'address': address}, function(results, status) {
                if (status == google.maps.GeocoderStatus.OK) {
                  if (status != google.maps.GeocoderStatus.ZERO_RESULTS) {
                    var result = results[0];
                    map.setCenter(result.geometry.location);

                    var infowindow = new google.maps.InfoWindow(
                        { content: '<b>'+result.formatted_address+'</b>',
                        size: new google.maps.Size(150, 50)
                    });

                    var marker = new google.maps.Marker({
                        position: result.geometry.location,
                        map: map,
                        title:address,
                    });
                    google.maps.event.addListener(marker, 'click', function() {
                        infowindow.open(map, marker);
                    });
                } else {
                    $('#map').hide();
                }
            } else {
              $('#map').hide();
          }
      });
    }

    google.maps.event.addDomListener(window, 'load', initialize);
    @endif

	</script>

@stop
