@extends('header')

@section('content')
	@parent

	<style type="text/css">

	#logo {
		padding-top: 6px;
	}

	</style>

	{!! Former::open_for_files()
            ->addClass('warn-on-exit')
            ->autocomplete('on')
            ->rules([
                'name' => 'required',
            ]) !!}

	{{ Former::populate($account) }}

    @include('accounts.nav', ['selected' => ACCOUNT_COMPANY_DETAILS])

	<div class="row">
		<div class="col-md-12">

        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.details') !!}</h3>
          </div>
            <div class="panel-body form-padding-right">

                {!! Former::text('name') !!}
                {!! Former::text('id_number') !!}
                {!! Former::text('vat_number') !!}
                {!! Former::text('website') !!}
                {!! Former::text('work_email') !!}
                {!! Former::text('work_phone') !!}
                {!! Former::file('logo')->max(2, 'MB')->accept('image')->inlineHelp(trans('texts.logo_help')) !!}


                @if ($account->hasLogo())
                <div class="form-group">
                    <div class="col-lg-4 col-sm-4"></div>
                    <div class="col-lg-8 col-sm-8">
                        <a href="{{ $account->getLogoUrl(true) }}" target="_blank">
                            {!! HTML::image($account->getLogoUrl(true), 'Logo', ['style' => 'max-width:300px']) !!}
                        </a> &nbsp;
                        <a href="#" onclick="deleteLogo()">{{ trans('texts.remove_logo') }}</a>
                    </div>
                </div>
                @endif


                {!! Former::select('size_id')
                        ->addOption('','')
                        ->fromQuery($sizes, 'name', 'id') !!}

                {!! Former::select('industry_id')
                        ->addOption('','')
                        ->fromQuery($industries, 'name', 'id')
                        ->help('texts.industry_help') !!}

            </div>
        </div>

        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.address') !!}</h3>
          </div>
            <div class="panel-body form-padding-right">

            {!! Former::text('address1')->autocomplete('address-line1') !!}
            {!! Former::text('address2')->autocomplete('address-line2') !!}
            {!! Former::text('city')->autocomplete('address-level2') !!}
            {!! Former::text('state')->autocomplete('address-level1') !!}
            {!! Former::text('postal_code')->autocomplete('postal-code') !!}
            {!! Former::select('country_id')
                    ->addOption('','')
                    ->fromQuery($countries, 'name', 'id') !!}

            </div>
        </div>

        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.defaults') !!}</h3>
          </div>
            <div class="panel-body form-padding-right">

                {!! Former::select('payment_type_id')
                        ->addOption('','')
                        ->fromQuery($paymentTypes, 'name', 'id')
                        ->help(trans('texts.payment_type_help')) !!}

                {!! Former::select('payment_terms')
                        ->addOption('','')
                        ->fromQuery(\App\Models\PaymentTerm::getSelectOptions(), 'name', 'num_days')
                        ->help(trans('texts.payment_terms_help') . ' | ' . link_to('/settings/payment_terms', trans('texts.customize_options'))) !!}

            </div>
        </div>
        </div>


	</div>

	<center>
        {!! Button::success(trans('texts.save'))->submit()->large()->appendIcon(Icon::create('floppy-disk')) !!}
	</center>

    {!! Former::close() !!}

	{!! Form::open(['url' => 'remove_logo', 'class' => 'removeLogoForm']) !!}
	{!! Form::close() !!}


	<script type="text/javascript">

        $(function() {
            $('#country_id').combobox();
        });

        function deleteLogo() {
            sweetConfirm(function() {
                $('.removeLogoForm').submit();
            });
        }

	</script>

@stop

@section('onReady')
    $('#name').focus();
@stop
