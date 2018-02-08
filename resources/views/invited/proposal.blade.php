@extends('public.header')

@section('content')

	<div class="container" style="padding: 20px;">
		<div class="pull-right">
			@if (! $proposal->invoice->isApproved())
				{!! Button::success(trans('texts.approve'))->withAttributes(['id' => 'approveButton', 'onclick' => 'onApproveClick()'])->large() !!}
			@endif
		</div>
		<div class="clearfix"></div><br/>
		<iframe src="{{ url('/proposal/' . $proposalInvitation->invitation_key . '?raw=true') }}" scrolling="no" onload="resizeIframe(this)"
			frameborder="0" width="100%" height="1000px" style="background-color:white; border: solid 1px #DDD;"></iframe>
    </div>

	<script type="text/javascript">

	function onApproveClick() {
		@if ($account->requiresAuthorization($proposal->invoice))
			window.pendingPaymentFunction = approveQuote;
			showAuthorizationModal();
		@else
			approveQuote();
		@endif
	}

	function approveQuote() {
		$('#approveButton').prop('disabled', true);
		location.href = "{{ url('/approve/' . $invitation->invitation_key) }}";
	}

	function resizeIframe(obj) {
		obj.style.height = obj.contentWindow.document.body.scrollHeight + 'px';
	}

	</script>

@stop
