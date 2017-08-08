@extends('header')

@section('content')
	@parent

    @include('accounts.nav', ['selected' => ACCOUNT_NOTIFICATIONS])

	{!! Former::open()->addClass('warn-on-exit') !!}
	{{ Former::populate($account) }}

	@include('accounts.partials.notifications')

	<div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">{!! trans('texts.google_analytics') !!}</h3>
      </div>
        <div class="panel-body">

			{!! Former::text('analytics_key')
			 		->help(trans('texts.analytics_key_help', ['link' => link_to('https://support.google.com/analytics/answer/1037249?hl=en', 'Google Analytics Ecommerce', ['target' => '_blank'])])) !!}

		</div>
    </div>

    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">{!! trans('texts.facebook_and_twitter') !!}</h3>
      </div>
        <div class="panel-body">


            <div class="form-group">
                <label for="notify_sent" class="control-label col-lg-4 col-sm-4">&nbsp;</label>
                <div class="col-lg-8 col-sm-8">

                    <div id="fb-root"></div>
                    <script>(function(d, s, id) {
                        var js, fjs = d.getElementsByTagName(s)[0];
                        if (d.getElementById(id)) return;
                        js = d.createElement(s); js.id = id;
                        js.src = "//connect.facebook.net/en_US/all.js#xfbml=1&appId=635126583203143";
                        fjs.parentNode.insertBefore(js, fjs);
                    }(document, 'script', 'facebook-jssdk'));</script>

                    <div class="fb-follow" data-href="https://www.facebook.com/invoiceninja" data-colorscheme="light" data-layout="button" data-show-faces="false" data-size="large"></div>&nbsp;&nbsp;

                    <a href="https://twitter.com/invoiceninja" class="twitter-follow-button" data-show-count="false" data-related="hillelcoren" data-size="large">Follow @invoiceninja</a>
                    <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>

                    <div class="help-block">{{ trans('texts.facebook_and_twitter_help') }}</div>

                </div>
            </div>
        </div>
    </div>


    {!! Former::actions(
            Button::success(trans('texts.save'))
                ->submit()->large()
                ->appendIcon(Icon::create('floppy-disk'))) !!}

	{!! Former::close() !!}


@stop
