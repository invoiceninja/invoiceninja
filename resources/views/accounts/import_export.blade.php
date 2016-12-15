@extends('header')

@section('head')
    @parent

    <style type="text/css">
        .import-file {
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
                    ->addGroupClass("import-file {$entityType}-file") !!}
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
                ->onchange('setCheckboxesEnabled()')
                ->addOption('CSV', 'CSV')
                ->addOption('XLS', 'XLS')
                ->addOption('JSON', 'JSON')
                ->style('max-width: 200px')
                ->help('<br/>' . trans('texts.export_help')) !!}


        {!! Former::inline_radios('include_radio')
                ->onchange('setCheckboxesEnabled()')
                ->label(trans('texts.include'))
                ->radios([
                    trans('texts.all') . ' &nbsp; ' => ['value' => 'all', 'name' => 'include'],
                    trans('texts.selected') => ['value' => 'selected', 'name' => 'include'],
                ])->check('all') !!}


        <div class="form-group entity-types">
            <label class="control-label col-lg-4 col-sm-4"></label>
            <div class="col-lg-2 col-sm-2">
                @include('partials/checkbox', ['field' => 'clients'])
                @include('partials/checkbox', ['field' => 'contacts'])
                @include('partials/checkbox', ['field' => 'credits'])
                @include('partials/checkbox', ['field' => 'tasks'])
            </div>
            <div class="col-lg-2 col-sm-2">
                @include('partials/checkbox', ['field' => 'invoices'])
                @include('partials/checkbox', ['field' => 'quotes'])
                @include('partials/checkbox', ['field' => 'recurring'])
                @include('partials/checkbox', ['field' => 'payments'])
            </div>
            <div class="col-lg-3 col-sm-3">
                @include('partials/checkbox', ['field' => 'products'])
                @include('partials/checkbox', ['field' => 'expenses'])
                @include('partials/checkbox', ['field' => 'vendors'])
                @include('partials/checkbox', ['field' => 'vendor_contacts'])
            </div>
        </div><br/>

        {!! Former::actions( Button::primary(trans('texts.download'))->submit()->large()->appendIcon(Icon::create('download-alt'))) !!}
    </div>
</div>
{!! Former::close() !!}


<script type="text/javascript">
  $(function() {
      setFileTypesVisible();
      setCheckboxesEnabled();
  });

  function setCheckboxesEnabled() {
      var $checkboxes = $('input[type=checkbox]');
      var include = $('input[name=include]:checked').val()
      var format = $('#format').val();
      if (include === 'all' || format === 'JSON') {
          $checkboxes.attr('disabled', true);
      } else {
          $checkboxes.removeAttr('disabled');
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
        @if ($source === IMPORT_JSON)
            if (val === '{{ $source }}') {
                $('.JSON-file').show();
            }
        @endif
    @endforeach
  }

</script>

@stop
