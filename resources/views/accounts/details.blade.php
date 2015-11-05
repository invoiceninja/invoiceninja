@extends('header')

@section('head')
    @parent

    <link href="{{ asset('css/quill.snow.css') }}" rel="stylesheet" type="text/css"/>
    <script src="{{ asset('js/quill.min.js') }}" type="text/javascript"></script>
@stop

@section('content')	
	@parent
	
	<style type="text/css">

	#logo {
		padding-top: 6px;
	}

	</style>

	{!! Former::open_for_files()->addClass('warn-on-exit')->rules(array(
  		'name' => 'required',
	)) !!}

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
    			{!! Former::text('work_email') !!}
    			{!! Former::text('work_phone') !!}
                {!! Former::file('logo')->max(2, 'MB')->accept('image')->inlineHelp(trans('texts.logo_help')) !!}


    			@if ($account->hasLogo())
    				<center>
    					{!! HTML::image($account->getLogoPath().'?no_cache='.time(), 'Logo', ['width' => 200]) !!} &nbsp;
    					<a href="#" onclick="deleteLogo()">{{ trans('texts.remove_logo') }}</a>
    				</center><br/>
    			@endif

    			{!! Former::select('size_id')->addOption('','')->fromQuery($sizes, 'name', 'id') !!}
    			{!! Former::select('industry_id')->addOption('','')->fromQuery($industries, 'name', 'id') !!}

            </div>
        </div>

        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.address') !!}</h3>
          </div>
            <div class="panel-body form-padding-right">
            
            {!! Former::text('address1') !!}
            {!! Former::text('address2') !!}
            {!! Former::text('city') !!}
            {!! Former::text('state') !!}
            {!! Former::text('postal_code') !!}
            {!! Former::select('country_id')->addOption('','')
                ->fromQuery($countries, 'name', 'id') !!}

            </div>
        </div>
        
        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title">{!! trans('texts.signature') !!}</h3>
          </div>
            <div class="panel-body">

                <div class="col-md-10 col-md-offset-1">
                    {!! Former::textarea('email_footer')->style('display:none')->raw() !!} 
                    <div id="signatureEditor" class="form-control" style="min-height:160px" onclick="focusEditor()"></div>
                    @include('partials/quill_toolbar', ['name' => 'signature'])
                </div>
            
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

        var editor = false;
        $(function() {
            $('#country_id').combobox();

            editor = new Quill('#signatureEditor', {
                modules: {
                    'toolbar': { container: '#signatureToolbar' },
                    'link-tooltip': true
                },
                theme: 'snow'
            });
            editor.setHTML($('#email_footer').val());
            editor.on('text-change', function(delta, source) {
                if (source == 'api') {
                    return;
                }
                var html = editor.getHTML();
                $('#email_footer').val(html);
                NINJA.formIsChanged = true;
            });
        });

        function focusEditor() {
            editor.focus();
        }

        function deleteLogo() {
            if (confirm("{!! trans('texts.are_you_sure') !!}")) {
                $('.removeLogoForm').submit();
            }
        }

	</script>

@stop

@section('onReady')
    $('#name').focus();
@stop