@extends('header')

@section('head')
    @parent

    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>
    <link href="{{ asset('css/select2.css') }}" rel="stylesheet" type="text/css"/>

    @if ($vendor->showMap())
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
          <li>{{ link_to('/vendors', trans('texts.vendors')) }}</li>
          <li class='active'>{{ $vendor->getDisplayName() }}</li> {!! $vendor->present()->statusLabel !!}
        </ol>
    </div>
    <div class="col-md-5">
        <div class="pull-right">

          {!! Former::open('vendors/bulk')->addClass('mainForm') !!}
      		<div style="display:none">
      			{!! Former::text('action') !!}
      			{!! Former::text('public_id')->value($vendor->public_id) !!}
      		</div>

              @if ( ! $vendor->is_deleted)
                  @can('edit', $vendor)
                      {!! DropdownButton::normal(trans('texts.edit_vendor'))
                          ->withAttributes(['class'=>'normalDropDown'])
                          ->withContents([
                            ($vendor->trashed() ? false : ['label' => trans('texts.archive_vendor'), 'url' => "javascript:onArchiveClick()"]),
                            ['label' => trans('texts.delete_vendor'), 'url' => "javascript:onDeleteClick()"],
                          ]
                        )->split() !!}
                  @endcan
                  @if ( ! $vendor->trashed())
                      @can('create', ENTITY_EXPENSE)
                          {!! Button::primary(trans("texts.new_expense"))
                                  ->asLinkTo(URL::to("/expenses/create/{$vendor->public_id}"))
                                  ->appendIcon(Icon::create('plus-sign')) !!}
                      @endcan
                  @endif
              @endif

              @if ($vendor->trashed())
                  @can('edit', $vendor)
                      {!! Button::primary(trans('texts.restore_vendor'))
                              ->appendIcon(Icon::create('cloud-download'))
                              ->withAttributes(['onclick' => 'onRestoreClick()']) !!}
                  @endcan
              @endif


      	  {!! Former::close() !!}

        </div>
    </div>
</div>



    <div class="panel panel-default">
    <div class="panel-body">
	<div class="row">
		<div class="col-md-3">
			<h3>{{ trans('texts.details') }}</h3>
            @if ($vendor->id_number)
                <p><i class="fa fa-id-number" style="width: 20px"></i>{{ trans('texts.id_number').': '.$vendor->id_number }}</p>
            @endif
            @if ($vendor->vat_number)
		  	   <p><i class="fa fa-vat-number" style="width: 20px"></i>{{ trans('texts.vat_number').': '.$vendor->vat_number }}</p>
            @endif

            @if ($vendor->address1)
                {{ $vendor->address1 }}<br/>
            @endif
            @if ($vendor->address2)
                {{ $vendor->address2 }}<br/>
            @endif
            @if ($vendor->getCityState())
                {{ $vendor->getCityState() }}<br/>
            @endif
            @if ($vendor->country)
                {{ $vendor->country->name }}<br/>
            @endif

            @if ($vendor->account->custom_vendor_label1 && $vendor->custom_value1)
                {{ $vendor->account->custom_vendor_label1 . ': ' . $vendor->custom_value1 }}<br/>
            @endif
            @if ($vendor->account->custom_vendor_label2 && $vendor->custom_value2)
                {{ $vendor->account->custom_vendor_label2 . ': ' . $vendor->custom_value2 }}<br/>
            @endif

            @if ($vendor->work_phone)
                <i class="fa fa-phone" style="width: 20px"></i>{{ $vendor->work_phone }}
            @endif

            @if ($vendor->private_notes)
                <p><i>{{ $vendor->private_notes }}</i></p>
            @endif

  	        @if ($vendor->vendor_industry)
                {{ $vendor->vendor_industry->name }}<br/>
            @endif
            @if ($vendor->vendor_size)
                {{ $vendor->vendor_size->name }}<br/>
            @endif

		  	@if ($vendor->website)
		  	   <p>{!! Utils::formatWebsite($vendor->website) !!}</p>
            @endif

            @if ($vendor->language)
                <p><i class="fa fa-language" style="width: 20px"></i>{{ $vendor->language->name }}</p>
            @endif

		  	<p>{{ $vendor->payment_terms ? trans('texts.payment_terms') . ": " . trans('texts.payment_terms_net') . " " . $vendor->payment_terms : '' }}</p>
		</div>

		<div class="col-md-3">
			<h3>{{ trans('texts.contacts') }}</h3>
		  	@foreach ($vendor->vendor_contacts as $contact)
                @if ($contact->first_name || $contact->last_name)
                    <b>{{ $contact->first_name.' '.$contact->last_name }}</b><br/>
                @endif
                @if ($contact->email)
                    <i class="fa fa-envelope" style="width: 20px"></i>{!! HTML::mailto($contact->email, $contact->email) !!}<br/>
                @endif
                @if ($contact->phone)
                    <i class="fa fa-phone" style="width: 20px"></i>{{ $contact->phone }}<br/>
                @endif
		  	@endforeach
		</div>

		<div class="col-md-4">
			<h3>{{ trans('texts.standing') }}
			<table class="table" style="width:100%">
				<tr>
					<td style="vertical-align: top"><small>{{ trans('texts.balance') }}</small></td>
                    <td style="text-align: right">
                        @foreach ($vendor->getTotalExpenses() as $currency)
                            <p>{{ Utils::formatMoney($currency->amount, $currency->expense_currency_id) }}</p>
                        @endforeach
                    </td>
				</tr>
			</table>
			</h3>
		</div>
	</div>
    </div>
    </div>

    @if ($vendor->showMap())
        <div id="map"></div>
        <br/>
    @endif

	<ul class="nav nav-tabs nav-justified">
		{!! Form::tab_link('#expenses', trans('texts.expenses')) !!}
	</ul><br/>

	<div class="tab-content">
        <div class="tab-pane" id="expenses">
            @include('list', [
                'entityType' => ENTITY_EXPENSE,
                'datatable' => new \App\Ninja\Datatables\ExpenseDatatable(true, true),
                'vendorId' => $vendor->public_id,
            ])
        </div>
    </div>

	<script type="text/javascript">

    var loadedTabs = {};

	$(function() {
		$('.normalDropDown:not(.dropdown-toggle)').click(function() {
			window.location = '{{ URL::to('vendors/' . $vendor->public_id . '/edit') }}';
		});
		$('.primaryDropDown:not(.dropdown-toggle)').click(function() {
			window.location = '{{ URL::to('expenses/create/' . $vendor->public_id ) }}';
		});

        $('.nav-tabs a[href="#expenses"]').tab('show');
        //load_expenses();
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

    @if ($vendor->showMap())
        function initialize() {
            var mapCanvas = document.getElementById('map');
            var mapOptions = {
                zoom: {{ DEFAULT_MAP_ZOOM }},
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                zoomControl: true,
            };

            var map = new google.maps.Map(mapCanvas, mapOptions)
            var address = "{{ "{$vendor->address1} {$vendor->address2} {$vendor->city} {$vendor->state} {$vendor->postal_code} " . ($vendor->country ? $vendor->country->name : '') }}";

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
