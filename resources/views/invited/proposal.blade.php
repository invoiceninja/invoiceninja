@extends('public.header')

@section('content')

	<script type="text/javascript">

	function onApproveClick() {
		$('#approveButton').prop('disabled', true);
		location.href = "{{ url('/approve/' . $invoiceInvitation->invitation_key) }}";
	}

	function resizeIframe(obj) {
		obj.style.height = obj.contentWindow.document.body.scrollHeight + 'px';
	}

	$(function() {
		var content = {!! json_encode($proposal->present()->htmlDocument) !!};
		var iFrame = document.getElementById('proposalIframe').contentWindow.document;

		iFrame.write(content);
		iFrame.close();
	})

	</script>

	<div class="container" style="padding: 20px;">
		@if ($message = $proposal->invoice->client->customMessage($proposal->getCustomMessageType()))
			@include('invited.custom_message', ['message' => $message])
        @endif

		<div class="pull-right">
			{!! Button::normal(trans('texts.download'))->asLinkTo(url("/proposal/{$proposalInvitation->invitation_key}/download"))->large() !!}
			@if (! $proposal->invoice->isApproved())
				{!! Button::success(trans('texts.approve'))->withAttributes(['id' => 'approveButton', 'onclick' => 'onApproveClick()'])->large() !!}
			@endif
		</div>
		<div class="clearfix"></div><br/>
		<iframe id="proposalIframe" scrolling="no" onload="resizeIframe(this)" frameborder="0" width="100%"
			style="background-color:white; border: solid 1px #DDD;"></iframe>
    </div>

@stop
