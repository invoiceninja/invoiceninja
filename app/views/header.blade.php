@extends('master')

	

@section('head')

	<meta name="csrf-token" content="<?= csrf_token() ?>">

	<script src="{{ asset('vendor/jquery-ui/ui/minified/jquery-ui.min.js') }}" type="text/javascript"></script>				
	<script src="{{ asset('vendor/bootstrap/dist/js/bootstrap.min.js') }}" type="text/javascript"></script>				
	<script src="{{ asset('vendor/datatables/media/js/jquery.dataTables.js') }}" type="text/javascript"></script>
    <script src="{{ asset('vendor/datatables-bootstrap3/BS3/assets/js/datatables.js') }}" type="text/javascript"></script>
    <script src="{{ asset('vendor/knockout.js/knockout.js') }}" type="text/javascript"></script>
    <script src="{{ asset('vendor/knockout-mapping/build/output/knockout.mapping-latest.js') }}" type="text/javascript"></script>
    <script src="{{ asset('vendor/knockout-sortable/build/knockout-sortable.min.js') }}" type="text/javascript"></script>
	<script src="{{ asset('vendor/underscore/underscore-min.js') }}" type="text/javascript"></script>		
	<script src="{{ asset('vendor/bootstrap-datepicker/js/bootstrap-datepicker.js') }}" type="text/javascript"></script>		
	<script src="{{ asset('vendor/typeahead.js/dist/typeahead.min.js') }}" type="text/javascript"></script>	
	<script src="{{ asset('vendor/accounting/accounting.min.js') }}" type="text/javascript"></script>		
	<script src="{{ asset('js/bootstrap-combobox.js') }}" type="text/javascript"></script>		
	<script src="{{ asset('js/jspdf.source.js') }}" type="text/javascript"></script>		
	<script src="{{ asset('js/jspdf.plugin.split_text_to_size.js') }}" type="text/javascript"></script>		
	<script src="{{ asset('js/script.js') }}" type="text/javascript"></script>		

	<link href="{{ asset('vendor/datatables/media/css/jquery.dataTables.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('vendor/datatables-bootstrap3/BS3/assets/css/datatables.css') }}" rel="stylesheet" type="text/css">    
	<link href="{{ asset('vendor/font-awesome/css/font-awesome.min.css') }}" rel="stylesheet" type="text/css"/>
	<link href="{{ asset('vendor/bootstrap-datepicker/css/datepicker.css') }}" rel="stylesheet" type="text/css"/>	
	<link href="{{ asset('css/bootstrap-combobox.css') }}" rel="stylesheet" type="text/css"/>	
	<link href="{{ asset('css/typeahead.js-bootstrap.css') }}" rel="stylesheet" type="text/css"/>			
	<link href="{{ asset('css/style.css') }}" rel="stylesheet" type="text/css"/>    

		
	<style type="text/css">

	@if (!Auth::check() || Auth::user()->showGreyBackground())
	body {
		/* background-color: #F6F6F6; */
		background-color: #EEEEEE;
	}
	@endif

	</style>

	<script type="text/javascript">

		var currencies = {{ Currency::remember(120)->get(); }};
		var currencyMap = {};
		for (var i=0; i<currencies.length; i++) {
			var currency = currencies[i];
			currencyMap[currency.id] = currency;
		}				
		function formatMoney(value, currency_id, hide_symbol) {
			if (!currency_id) currency_id = {{ Session::get(SESSION_CURRENCY, DEFAULT_CURRENCY); }};
			var currency = currencyMap[currency_id];
			return accounting.formatMoney(value, hide_symbol ? '' : currency.symbol, currency.precision, currency.thousand_separator, currency.decimal_separator);
		}
	</script>


@stop

@section('body')
		
	<div class="container">
	<p>&nbsp;</p>
	<p>&nbsp;</p>
	<nav class="navbar navbar-default navbar-fixed-top" role="navigation">

	  <div class="navbar-header">
	    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navbar-collapse-1">
	      <span class="sr-only">Toggle navigation</span>
	      <span class="icon-bar"></span>
	      <span class="icon-bar"></span>
	      <span class="icon-bar"></span>
	    </button>
	    {{ link_to('/', 'Invoice Ninja', array('class'=>'navbar-brand')) }}
	  </div>

	  <div class="collapse navbar-collapse" id="navbar-collapse-1">
	    <ul class="nav navbar-nav" style="font-weight: bold">
	    	{{-- HTML::nav_link('home', 'Home') --}}
	    	{{ HTML::menu_link('client') }}
	    	{{ HTML::menu_link('invoice') }}
	    	{{ HTML::menu_link('payment') }}
	    	{{ HTML::menu_link('credit') }}
	    	{{-- HTML::nav_link('reports', 'Reports') --}}
	    </ul>

		<div class="navbar-form navbar-right">
			@if (Auth::check() && !Auth::user()->registered)
				{{ Button::sm_success_primary('Sign up', array('data-toggle'=>'modal', 'data-target'=>'#signUpModal')) }} &nbsp;
			@endif
			
			@if (Auth::check())
			<div class="btn-group">
			  <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown">
			  @if (Auth::check() && Auth::user()->registered)
			  {{ Auth::user()->getFullName() }}
			  @else			  
			    My Account 
			  @endif
			  <span class="caret"></span>
			  </button>			
			  <ul class="dropdown-menu" role="menu">
			    <li>{{ link_to('account/details', 'Details') }}</li>
			    <li>{{ link_to('account/settings', 'Settings') }}</li>
			    <li>{{ link_to('account/import', 'Import') }}</li>
			    <li>{{ link_to('account/export', 'Export') }}</li>
			    <li class="divider"></li>
			    <li>{{ link_to('#', 'Logout', array('onclick'=>'logout()')) }}</li>
			  </ul>
			</div>
			@endif			
		</div>	

		<ul class="nav navbar-nav navbar-right">	      
	      <li class="dropdown">
	        <a href="#" class="dropdown-toggle" data-toggle="dropdown">Recently Viewed <b class="caret"></b></a>
	        <ul class="dropdown-menu">	        		        	
	        	@if (count(Session::get(RECENTLY_VIEWED)) == 0)
	          		<li><a href="#">No items</a></li>
	          	@else
	          		@foreach (Session::get(RECENTLY_VIEWED) as $link)
	          			<li><a href="{{ $link->url }}">{{ $link->name }}</a></li>	
	          		@endforeach
	          	@endif
	        </ul>
	      </li>
	    <form class="navbar-form navbar-left" role="search">
	      <div class="form-group">
	        <input type="text" id="search" class="form-control" placeholder="Search">
	      </div>
	    </form>
	    </ul>	    



	  </div><!-- /.navbar-collapse -->
	</nav>
	
	<p>&nbsp;</p>

	@if (Session::has('message'))
		<div class="alert alert-info">{{ Session::get('message') }}</div>
	@endif

	@yield('content')		


		</div>
		<div class="container">
		<div class="footer" style="padding-top: 32px">
			@if (false)
	      <div class="pull-right">
		      	{{ Former::open('user/setTheme')->addClass('themeForm') }}
		      	<div style="display:none">
			      	{{ Former::text('theme_id') }}
			      	{{ Former::text('path')->value(Request::url()) }}
			    </div>
		      	<div class="btn-group tr-action dropup">
					<button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
						Site Theme <span class="caret"></span>
					</button>
					<ul class="dropdown-menu" role="menu">
					<li><a href="#" onclick="setTheme(0)">Default</a></li>
					@foreach (Theme::remember(DEFAULT_QUERY_CACHE)->get() as $theme)
						<li><a href="#" onclick="setTheme({{ $theme->id }})">{{ ucwords($theme->name) }}</a></li>
					@endforeach
				  </ul>
				</div>
		      	{{ Former::close() }}	      	
		    </div>
		    @endif

		    Want something changed? We're {{ link_to('https://github.com/hillelcoren/invoice-ninja', 'open source', array('target'=>'_blank')) }}, email us at {{ link_to('mailto:contact@invoiceninja.com', 'contact@invoiceninja.com') }}.			

		</div>			
		</div>
	</div>


	@if (!Auth::check() || !Auth::user()->registered)
	<div class="modal fade" id="signUpModal" tabindex="-1" role="dialog" aria-labelledby="signUpModalLabel" aria-hidden="true">
	  <div class="modal-dialog">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	        <h4 class="modal-title" id="myModalLabel">Sign up</h4>
	      </div>

	      <div style="padding-right:20px" id="signUpDiv" onkeyup="validateSignUp()" onkeydown="checkForEnter(event)">
	    	
	    	{{ Former::open('signup/submit')->addClass('signUpForm') }}

	    	@if (Auth::check())
	    		{{ Former::populateField('new_first_name', Auth::user()->first_name); }}
	    		{{ Former::populateField('new_last_name', Auth::user()->last_name); }}
	    		{{ Former::populateField('new_email', Auth::user()->email); }}	    		
	    	@endif
	    	{{ Former::hidden('path')->value(Request::path()) }}
	    	{{ Former::text('new_first_name')->label('First name') }}
	    	{{ Former::text('new_last_name')->label('Last name') }}
	    	{{ Former::text('new_email')->label('Email') }}	    	
			{{ Former::password('new_password')->label('Password') }}
			{{ Former::close() }}
			<center><div id="errorTaken" style="display:none">&nbsp;<br/>The email address is already regiestered</div></center>
		  </div>

		  <div style="padding-left:40px;padding-right:40px;display:none;min-height:130px" id="working">
		  	<h3>Working...</h3>
		  	<div class="progress progress-striped active">
	  			<div class="progress-bar"  role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%"></div>
			</div>
		  </div>

	      <div class="modal-footer" id="signUpFooter">	      	
	      	<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
	        <button type="button" class="btn btn-primary" onclick="submitSignUp()">Save</button>	      	
	      </div>
	    </div>
	  </div>
	</div>

	
	<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
	  <div class="modal-dialog">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	        <h4 class="modal-title" id="myModalLabel">Logout</h4>
	      </div>

	      <div class="container">	     
	      	<h3>Are you sure?</h3>
	      	<p>This will permanently erase your data.</p>	      	
	      </div>

	      <div class="modal-footer" id="signUpFooter">	      	
	      	<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
	        <button type="button" class="btn btn-primary" onclick="logout(true)">Logout</button>	      	
	      </div>
	    </div>
	  </div>
	</div>
	@endif
	
		

  </body>


  <script type="text/javascript">

  		function setTheme(id)
  		{
  			$('#theme_id').val(id);
  			$('form.themeForm').submit();
  		}

		@if (!Auth::check() || !Auth::user()->registered)
  		function validateSignUp(showError) 
  		{
  			var isFormValid = true;
  			$(['first_name','last_name','email','password']).each(function(i, field) {
  				var $input = $('form.signUpForm #new_'+field),
  					val = $.trim($input.val());
  				var isValid = val && val.length >= (field == 'password' ? 6 : 1);
  				if (isValid && field == 'email') {
  					isValid = isValidEmailAddress(val);
  				}
  				if (isValid) {
  					$input.closest('div.form-group').removeClass('has-error').addClass('has-success');
  				} else {
  					isFormValid = false;
  					$input.closest('div.form-group').removeClass('has-success');
  					if (showError) {
  						$input.closest('div.form-group').addClass('has-error');
  					}
  				}
  			});
  			return isFormValid;
  		}

  		function submitSignUp()
  		{
  			if (!validateSignUp(true)) {
  				return;
  			}

  			$('#signUpDiv, #signUpFooter').hide();
  			$('#working').show();

			$.ajax({
				type: 'POST',
				url: '{{ URL::to('signup/validate') }}',
				data: 'email=' + $('form.signUpForm #new_email').val() + '&path={{ Request::path() }}',
				success: function(result) { 
					if (result == 'available') {
						$('.signUpForm').submit();
					} else {
						$('#errorTaken').show();
  						$('form.signUpForm #email').closest('div.form-group').removeClass('has-success').addClass('has-error');
  						$('#signUpDiv, #signUpFooter').show();
			  			$('#working').hide();
					}
				}
			});			
  		}

  		function checkForEnter(event)
  		{
			if (event.keyCode === 13){
				event.preventDefault();		     	
	            submitSignUp();
	            return false;
	        }
        }
  		@endif

  		function logout(force)
  		{
  			if (force || {{ !Auth::check() || Auth::user()->registered ? 'true' : 'false' }}) {
  				window.location = '{{ URL::to('logout') }}';
  			} else {
  				$('#logoutModal').modal('show');	
  			}
  		}

  		$(function() 
  		{
  			$('#search').focus(function(){
  				if (!window.hasOwnProperty('searchData')) {
  					$.get('{{ URL::route('getSearchData') }}', function(data) {  						
  						window.searchData = true;						
  						var datasets = [];
  						for (var type in data)
  						{  							
  							if (!data.hasOwnProperty(type)) continue;  							
  							datasets.push({
  								name: type,
  								header: '&nbsp;<b>' + type  + '</b>',  								
  								local: data[type]
  							});  														
  						}
  						if (datasets.length == 0) {
  							return;
  						}
  						$('#search').typeahead(datasets).on('typeahead:selected', function(element, datum, name) {
  							var type = name == 'Contacts' ? 'clients' : name.toLowerCase();
  							window.location = '{{ URL::to('/') }}' + '/' + type + '/' + datum.public_id;
  						}).focus().typeahead('setQuery', $('#search').val());  						
					});
  				}
  			});
			

	      	if (isStorageSupported()) {
	  			@if (Auth::check() && !Auth::user()->registered)
	        		localStorage.setItem('guest_key', '{{ Auth::user()->password }}');
	        	@elseif (Session::get('clearGuestKey'))
	        		localStorage.setItem('guest_key', '');
				@endif
        	}
  	
			@if (!Auth::check() || !Auth::user()->registered)
	  			validateSignUp();

				$('#signUpModal').on('shown.bs.modal', function () {
		  			$(['first_name','last_name','email','password']).each(function(i, field) {
		  				var $input = $('form.signUpForm #new_'+field);
		  				if (!$input.val()) {
		  					$input.focus();	  					
		  					return false;
		  				}
		  			});
				})

				/*
				$(window).on('beforeunload', function() {
					return true;
				});	
				$('form').submit(function() { $(window).off('beforeunload') });
				$('a[rel!=ext]').click(function() { $(window).off('beforeunload') });
				*/
  			@endif

  			@if (Session::has('message'))
  				setTimeout(function() {
  					$('.alert-info').fadeOut();
  				}, 3000);
  			@endif		

  			@yield('onReady')
  		});

  </script>  

@stop