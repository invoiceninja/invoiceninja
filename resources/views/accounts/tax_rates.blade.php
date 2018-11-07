@extends('header')

@section('content')
  @parent

  @include('accounts.nav', ['selected' => ACCOUNT_TAX_RATES])

  {!! Former::open()->addClass('warn-on-exit') !!}
  {{ Former::populate($account) }}
  {{ Former::populateField('invoice_taxes', intval($account->invoice_taxes)) }}
  {{ Former::populateField('invoice_item_taxes', intval($account->invoice_item_taxes)) }}
  {{ Former::populateField('enable_second_tax_rate', intval($account->enable_second_tax_rate)) }}
  {{ Former::populateField('include_item_taxes_inline', intval($account->include_item_taxes_inline)) }}
  {{ Former::populateField('inclusive_taxes', intval($account->inclusive_taxes)) }}


  <div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title">{!! trans('texts.tax_settings') !!}</h3>
  </div>
  <div class="panel-body">

    {!! Former::checkbox('invoice_taxes')
        ->text(trans('texts.enable_invoice_tax'))
        ->label('&nbsp;')
        ->value(1) !!}

    {!! Former::checkbox('invoice_item_taxes')
        ->text(trans('texts.enable_line_item_tax'))
        ->label('&nbsp;')
        ->value(1) !!}

    {!! Former::checkbox('enable_second_tax_rate')
        ->text(trans('texts.enable_second_tax_rate'))
        ->label('&nbsp;')
        ->value(1) !!}

    {!! Former::checkbox('include_item_taxes_inline')
        ->text(trans('texts.include_item_taxes_inline'))
        ->label('&nbsp;')
        ->value(1) !!}

    @if (! $hasInclusiveTaxRates && $countInvoices == 0)
        <br/>
        <br/>
        {!! Former::checkbox('inclusive_taxes')
            ->text(trans('texts.inclusive_taxes_help'))
            ->label('&nbsp;')
            ->help('<b>' . strtoupper(trans('texts.important')) . ': '
                . trans('texts.inclusive_taxes_notice') . '</b>')
            ->value(1) !!}
    @elseif ($countInvoices <= 10)
        {!! Former::plaintext(' ')->help(
                trans($account->inclusive_taxes ? 'texts.taxes_are_included_help' : 'texts.taxes_are_not_included_help') . '<br/>' .
                trans('texts.change_requires_purge', ['link' => link_to(url('/settings/account_management'), trans('texts.purging'))])) !!}
    @else
        {!! Former::plaintext(' ')->help(
                trans($account->inclusive_taxes ? 'texts.taxes_are_included_help' : 'texts.taxes_are_not_included_help'))  !!}
    @endif

      &nbsp;

      @if ($taxRates->count())
          @include('partials.tax_rates', ['taxRateLabel' => trans('texts.default_tax_rate_id')])
          &nbsp;
      @endif

      {!! Former::actions( Button::success(trans('texts.save'))->submit()->appendIcon(Icon::create('floppy-disk')) ) !!}
      {!! Former::close() !!}
  </div>
  </div>

  {!! Button::primary(trans('texts.create_tax_rate'))
        ->asLinkTo(URL::to('/tax_rates/create'))
        ->withAttributes(['class' => 'pull-right'])
        ->appendIcon(Icon::create('plus-sign')) !!}

  @include('partials.bulk_form', ['entityType' => ENTITY_TAX_RATE])

  {!! Datatable::table()
      ->addColumn(
        trans('texts.name'),
        trans('texts.rate'),
        trans('texts.type'),
        trans('texts.action'))
      ->setUrl(url('api/tax_rates/'))
      ->setOptions('sPaginationType', 'bootstrap')
      ->setOptions('bFilter', false)
      ->setOptions('bAutoWidth', false)
      ->setOptions('aoColumns', [[ "sWidth"=> "25%" ], [ "sWidth"=> "25%" ], ["sWidth"=> "25%"], ["sWidth"=> "25%"]])
      ->setOptions('aoColumnDefs', [['bSortable'=>false, 'aTargets'=>[2]]])
      ->render('datatable') !!}

  <script>
    window.onDatatableReady = actionListHandler;
  </script>


  <script type="text/javascript">
    $(function() {
        @if ($countInvoices > 0)
            $('#inclusive_taxes').change(function() {
                swal("{{ trans('texts.inclusive_taxes_warning') }}");
            })
        @endif
    })
  </script>

@stop
