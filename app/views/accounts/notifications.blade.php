@extends('accounts.nav')

@section('content')	
	@parent	

	{{ Former::open()->addClass('col-md-8 col-md-offset-2') }}	
	{{ Former::populate($account) }}
	{{ Former::populateField('notify_sent', intval(Auth::user()->notify_sent)) }}
	{{ Former::populateField('notify_viewed', intval(Auth::user()->notify_viewed)) }}
	{{ Former::populateField('notify_paid', intval(Auth::user()->notify_paid)) }}

	{{ Former::legend('Email Notifications') }}
	{{ Former::checkbox('notify_sent')->label('&nbsp;')->text('Email me when an invoice is <b>sent</b>') }}
	{{ Former::checkbox('notify_viewed')->label('&nbsp;')->text('Email me when an invoice is <b>viewed</b>') }}
	{{ Former::checkbox('notify_paid')->label('&nbsp;')->text('Email me when an invoice is <b>paid</b>') }}

	{{ Former::legend('Site Updates') }}

	<div class="form-group">
		<label for="invoice_terms" class="control-label col-lg-4 col-sm-4"></label>
		<div class="col-lg-8 col-sm-8">

			<div id="fb-root"></div>
			<script>(function(d, s, id) {
			  var js, fjs = d.getElementsByTagName(s)[0];
			  if (d.getElementById(id)) return;
			  js = d.createElement(s); js.id = id;
			  js.src = "//connect.facebook.net/en_US/all.js#xfbml=1&appId=635126583203143";
			  fjs.parentNode.insertBefore(js, fjs);
			}(document, 'script', 'facebook-jssdk'));</script>

			<div class="fb-follow" data-href="https://www.facebook.com/invoiceninja" data-colorscheme="light" data-layout="button" data-show-faces="false"></div>&nbsp;&nbsp;
	  	<a href="https://twitter.com/invoiceninja" class="twitter-follow-button" data-show-count="false" data-size="medium">Follow @invoiceninja</a>
	  	<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>

  </div></div>

	{{ Former::legend('Custom Messages') }}
	{{ Former::textarea('invoice_terms')->label('Set default invoice terms') }}
	{{ Former::textarea('email_footer')->label('Set default email signature') }}

	{{ Former::actions( Button::lg_success_submit('Save')->append_with_icon('floppy-disk') ) }}
	{{ Former::close() }}

@stop