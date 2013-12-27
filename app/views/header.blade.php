@extends('master')

	

@section('head')

	<meta name="csrf-token" content="<?= csrf_token() ?>">
	<script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>
	@if (Auth::check() && Auth::user()->theme_id)
		<link rel="stylesheet" type="text/css" href="{{ asset('css/themes/'.Auth::user()->theme->name.'.min.css') }}"/>		
	@else
		<link rel="stylesheet" type="text/css" href="{{ asset('css/bootstrap.css') }}"/>
	@endif
	<script src="{{ asset('js/bootstrap.js') }}" type="text/javascript"></script>				

	<script src="{{ asset('js/bootstrap-combobox.js') }}" type="text/javascript"></script>		
	<link rel="stylesheet" type="text/css" href="{{ asset('css/bootstrap-combobox.css') }}"/>	

	<link rel="stylesheet" type="text/css" href="{{ asset('css/jquery.dataTables.css') }}">
    <script type="text/javascript" src="{{ asset('js/jquery.dataTables.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/knockout-3.0.0.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/knockout.mapping-latest.js') }}"></script>
    <script src="{{ asset('js/knockout-sortable.js') }}" type="text/javascript"></script>		
	<link rel="stylesheet" type="text/css" href="{{ asset('css/font-awesome.css') }}"/>
	
	<script src="{{ asset('js/jspdf.source.js') }}" type="text/javascript"></script>		
	<script src="{{ asset('js/jspdf.plugin.split_text_to_size.js') }}" type="text/javascript"></script>		
	
	<script src="{{ asset('js/bootstrap-datepicker.js') }}" type="text/javascript"></script>		
	<link rel="stylesheet" type="text/css" href="{{ asset('css/datepicker.css') }}"/>

	<script src="{{ asset('js/typeahead.js') }}" type="text/javascript"></script>	
	<link rel="stylesheet" type="text/css" href="{{ asset('css/typeahead.js-bootstrap.css') }}"/>			
	
	<script src="{{ asset('js/script.js') }}" type="text/javascript"></script>		

	<style type="text/css">

	@if (!Auth::check() || Auth::user()->showGreyBackground())
	body {
		background-color: #F6F6F6;
	}
	@endif

	/*
	body > div.container {
		min-height: 600px;		
	}
	*/
	
	label.checkbox,
	label.control-label {
		font-weight: normal !important;
	}




	div.panel {
		padding-left: 0px !important;
		padding-right: 0px !important;
	}

	.form-actions {
	    margin: 0;
	    background-color: transparent;
	    text-align: center;
	}

	/*
	.form-horizontal {
		max-width: 750px;
		margin: 0 auto;
	}
	*/

	/*
	.form-group {
		width: 50%;
    	margin: 0 auto;
	}
	*/

	/* DataTables and BootStrap */
	.dataTables_wrapper {
		padding-top: 16px;
	}

	table.table thead > tr > th {
		border-bottom-width: 0px;
	}

	#DataTables_Table_0_length label {
		font-weight: normal;
		padding-bottom: 10px;		
	}

	div.dataTables_paginate.paging_bootstrap  {
		margin-top: -30px;
	}

	/*
	table.table tbody tr.odd {
		background-color: #f9f9f9;
	}

	table.table tbody tr:hover {
		background-color: #f0f0f0 !important;
	}
	*/


	/* table sorting indicators */
	/*table.table thead .sorting { background: url('images/sort_both.png') no-repeat center right; }*/
	/*
	table.table thead .sorting,
	table.table thead .sorting_asc,
	table.table thead .sorting_desc,
	table.table thead .sorting_asc_disabled,
	table.table thead .sorting_desc_disabled {
	    cursor: pointer;
	    *cursor: hand;
	}
	 
	table.table thead .sorting_asc { background: url('images/sort_asc.png') no-repeat center right; }
	table.table thead .sorting_desc { background: url('images/sort_desc.png') no-repeat center right; }
	
	 
	table.table thead .sorting_asc_disabled { background: url('images/sort_asc_disabled.png') no-repeat center right; }
	table.table thead .sorting_desc_disabled { background: url('images/sort_desc_disabled.png') no-repeat center right; }
	*/
		

	/* Hover nav */
	.sidebar-nav {
	    padding: 9px 0;
	}

	.dropdown-menu .sub-menu {
	    left: 100%;
	    position: absolute;
	    top: 0;
	    visibility: hidden;
	    margin-top: -1px;
	}

	.dropdown-menu li:hover .sub-menu {
	    visibility: visible;
	}

	.dropdown:hover .dropdown-menu {
	    display: block;
	}

	.nav-tabs .dropdown-menu, .nav-pills .dropdown-menu, .navbar .dropdown-menu {
	    margin-top: 0;
	}

	.navbar .sub-menu:before {
	    border-bottom: 7px solid transparent;
	    border-left: none;
	    border-right: 7px solid rgba(0, 0, 0, 0.2);
	    border-top: 7px solid transparent;
	    left: -7px;
	    top: 10px;
	}
	.navbar .sub-menu:after {
	    border-top: 6px solid transparent;
	    border-left: none;
	    border-right: 6px solid #fff;
	    border-bottom: 6px solid transparent;
	    left: 10px;
	    top: 11px;
	    left: -6px;
	}


	.invoice-table {
		border-style: none !important;
	}

	/*
	table.invoice-table tbody tr:hover {
		background-color: #FFFFFF !important;
	}
	*/
	
	.invoice-table td {
		padding: 2px !important;
	}

	.invoice-table td input,
	.invoice-table td textarea {
		border: none !important;
		width: 100%;
	}

	.invoice-table th {
		border-top: 1px solid #ddd !important;		
	}

	.invoice-table td.hide-border,
	.invoice-table th.hide-border {
		border-style: none !important;
	}

	.invoice-table td.td-icon {
		vertical-align: middle !important;
	}

	.fa-bars {
		cursor: move !important;
	}

	.closer-row {
		margin-bottom: 2px;
	}


	/* Animate col width changes */
	.row div {
	    -webkit-transition: width 0.5s ease, margin 0.5s ease;
	    -moz-transition: width 0.5s ease, margin 0.5s ease;
	    -o-transition: width 0.5s ease, margin 0.5s ease;
	    transition: width 0.5s ease, margin 0.5s ease;	    
	}

	</style>

@stop

@section('body')
		
	<div class="container">
	<p/>
	<div>		
		<a href="{{ URL::to('/') }}" style="font-size:30px;color:black">Invoice Ninja</a>
	
	</div>

	<p style="clear: both"/>
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
	    	{{ HTML::nav_link('reports', 'Reports') }}
	    </ul>

		<div class="navbar-form navbar-right">
			@if (Auth::check() && Auth::user()->registered)
			{{ Auth::user()->email }} &nbsp;
			@else			
			{{ Button::sm_primary('Sign up', array('data-toggle'=>'modal', 'data-target'=>'#signUpModal')) }}
			@endif
			
			<div class="btn-group">
			  <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown">
			    My Account <span class="caret"></span>
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
	
	@if (Session::has('message'))
		<div class="alert alert-info">{{ Session::get('message') }}</div>
	@endif

	@yield('content')		


		</div>
		<div class="container">
		<div class="footer">
	      <div class="pull-right">
		      	{{ Former::open('user/setTheme')->addClass('themeForm') }}
		      	<div style="display:none">
			      	{{ Former::text('theme_id') }}
			      	{{ Former::text('path')->value(Request::url()) }}
			    </div>
		      	<div class="btn-group tr-action dropup">
					<button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
						Theme <span class="caret"></span>
					</button>
					<ul class="dropdown-menu" role="menu">
					<li><a href="#" onclick="setTheme(0)">Default</a></li>
					@foreach (Theme::all() as $theme)
						<li><a href="#" onclick="setTheme({{ $theme->id }})">{{ ucwords($theme->name) }}</a></li>
					@endforeach
				  </ul>
				</div>
		      	{{ Former::close() }}	      	
		    </div>

  			Powered by {{ link_to('https://github.com/hillelcoren/invoice-ninja', 'InvoiceNinja', array('target'=>'_blank')) }}
			<p class="text-danger">This is a demo site, the data is erased.</p>

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
	    		{{ Former::populate(Auth::user()) }}
	    	@endif
	    	{{ Former::hidden('path')->value(Request::path()) }}
	    	{{ Former::text('first_name') }}
	    	{{ Former::text('last_name') }}
	    	{{ Former::text('email') }}	    	
			{{ Former::password('password') }}
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
	      	<p>This will erase all of your data.</p>	
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
  				var $input = $('form.signUpForm #'+field),
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
				data: 'email=' + $('form.signUpForm #email').val() + '&path={{ Request::path() }}',
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
		  				var $input = $('form.signUpForm #'+field);
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

  			@yield('onReady')
  		});

  </script>  
  
@stop