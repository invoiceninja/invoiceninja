@extends('public.header')

@section('content')

	<div class="container" style="padding: 20px;">
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

	<script type="text/javascript">

	function onApproveClick() {
		$('#approveButton').prop('disabled', true);
		location.href = "{{ url('/approve/' . $invoiceInvitation->invitation_key) }}";
	}

	function resizeIframe(obj) {
		obj.style.height = obj.contentWindow.document.body.scrollHeight + 'px';
	}

	$(function() {
		var html = {!! json_encode($proposal->html) !!};
		var css = {!! json_encode($proposal->css) !!};

		var content = '<html><head><style>' + css + '</style></head><body>' + html + '</body></html>';
		var iFrame = document.getElementById('proposalIframe').contentWindow.document;

		iFrame.write(content);
		iFrame.close();
	})

	</script>

@stop
