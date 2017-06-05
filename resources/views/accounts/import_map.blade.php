@extends('header')

@section('content')
	@parent

    @include('accounts.nav', ['selected' => ACCOUNT_IMPORT_EXPORT])

	{!! Former::open('/import_csv')->addClass('warn-on-exit') !!}
	{!! Former::populateField('timestamp', $timestamp) !!}

	<div style="display:none">
		{!! Former::text('timestamp') !!}
	</div>

    @foreach (App\Services\ImportService::$entityTypes as $entityType)
        @if (isset($data[$entityType]))
            @include('accounts.partials.map', $data[$entityType])
        @endif
    @endforeach

    {!! Former::actions(
        Button::normal(trans('texts.cancel'))->large()->asLinkTo(URL::to('/cancel_import?timestamp=' . $timestamp))->appendIcon(Icon::create('remove-circle')),
        Button::success(trans('texts.import'))->submit()->large()->appendIcon(Icon::create('floppy-disk'))) !!}

    {!! Former::close() !!}

@stop
