@extends('header')

@section('head')
    @parent

    <style type="text/css">
        .contact-file,
        .task-file,
        .payment-file {
            display: none;
        }
    </style>
@stop


@section('content')
@parent

    @include('accounts.nav', ['selected' => ACCOUNT_IMPORT_EXPORT])

<div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title">{!! trans('texts.import_data') !!}</h3>
  </div>
    <div class="panel-body">

        {!! Former::open_for_files('/import')
                ->addClass('warn-on-exit') !!}

        {!! Former::select('source')
                ->onchange('setFileTypesVisible()')
                ->options(array_combine(\App\Services\ImportService::$sources, \App\Services\ImportService::$sources))
                ->style('width: 200px') !!}

        @foreach (\App\Services\ImportService::$entityTypes as $entityType)
            {!! Former::file("{$entityType}_file")
                    ->addGroupClass("{$entityType}-file") !!}
        @endforeach

        {!! Former::actions( Button::info(trans('texts.upload'))->submit()->large()->appendIcon(Icon::create('open'))) !!}
        {!! Former::close() !!}

    </div>
</div>


{!! Former::open('/export') !!}
<div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title">{!! trans('texts.export_data') !!}</h3>
  </div>
    <div class="panel-body">
        {!! Former::select('format')
                ->onchange('setEntityTypesVisible()')
                ->addOption('CSV', 'CSV')
                ->addOption('XLS', 'XLS')
                ->addOption('JSON', 'JSON')
                ->style('max-width: 200px') !!}

        {!! Former::checkbox('entity_types')
                ->label('include')
                ->addGroupClass('entity-types')
                ->checkboxes([
                    trans('texts.clients') => array('name' => ENTITY_CLIENT, 'value' => 1),
                    trans('texts.tasks') => array('name' => ENTITY_TASK, 'value' => 1),
                    trans('texts.invoices') => array('name' => ENTITY_INVOICE, 'value' => 1),
                    trans('texts.payments') => array('name' => ENTITY_PAYMENT, 'value' => 1),
                ])->check(ENTITY_CLIENT)->check(ENTITY_TASK)->check(ENTITY_INVOICE)->check(ENTITY_PAYMENT) !!}

        {!! Former::actions( Button::primary(trans('texts.download'))->submit()->large()->appendIcon(Icon::create('download-alt'))) !!}            
    </div>
</div>
{!! Former::close() !!}


<script type="text/javascript">
  function setEntityTypesVisible() {
    var selector = '.entity-types input[type=checkbox]';
    if ($('#format').val() === 'JSON') {
        $(selector).attr('disabled', true);
    } else {
        $(selector).removeAttr('disabled');
    }
  }

  function setFileTypesVisible() {
    var val = $('#source').val();
    @foreach (\App\Services\ImportService::$entityTypes as $entityType)
        $('.{{ $entityType }}-file').hide();
    @endforeach
    @foreach (\App\Services\ImportService::$sources as $source)
        if (val === '{{ $source }}') {
            @foreach (\App\Services\ImportService::$entityTypes as $entityType)
                @if ($source != IMPORT_WAVE && $entityType == ENTITY_PAYMENT)
                    // do nothing
                @elseif (class_exists(\App\Services\ImportService::getTransformerClassName($source, $entityType)))
                    $('.{{ $entityType }}-file').show();
                @endif
            @endforeach
        }
    @endforeach
  }

</script>

@stop