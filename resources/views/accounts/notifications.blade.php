@extends('header')

@section('content')
	@parent

    @include('accounts.nav', ['selected' => ACCOUNT_NOTIFICATIONS])

	{!! Former::open()
			->addClass('warn-on-exit')
			->rules([
				'slack_webhook_url' => 'url',
			]) !!}
	{{ Former::populate($account) }}
	{{ Former::populateField('slack_webhook_url', auth()->user()->slack_webhook_url) }}

	@include('accounts.partials.notifications')

	<div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">Slack</h3>
      </div>
        <div class="panel-body">

			{!! Former::text('slack_webhook_url')
					->label('webhook_url')
			 		->help(trans('texts.slack_webhook_help', ['link' => link_to('https://my.slack.com/services/new/incoming-webhook/', trans('texts.slack_incoming_webhooks'), ['target' => '_blank'])])) !!}

		</div>
    </div>

	<div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">{!! trans('texts.google_analytics') !!}</h3>
      </div>
        <div class="panel-body">

			{!! Former::text('analytics_key')
			 		->help(trans('texts.analytics_key_help', ['link' => link_to('https://support.google.com/analytics/answer/1037249?hl=en', 'Google Analytics Ecommerce', ['target' => '_blank'])])) !!}

		</div>
    </div>

    <center class="buttons">
        {!! Button::success(trans('texts.save'))
                ->submit()->large()
                ->appendIcon(Icon::create('floppy-disk')) !!}
	</center>

	{!! Former::close() !!}


@stop
