@extends('portal.default.layouts.master')
@section('body')
<main class="main">
    <div class="container-fluid">

{!! Former::framework('TwitterBootstrap4'); !!}

{!! Former::horizontal_open()
    ->id('payment_form')
    ->route('client.invoices.bulk')
    ->method('POST');    !!}

{!! Former::hidden('hashed_ids')->id('hashed_ids')->value( $invoice->hashed_id ) !!}
{!! Former::hidden('action')->id('action')->value('payment') !!}



    	<div class="row mt-4">
	    	<div class="col-md-12">

	    		@if($invoice->isPayable())
	    		<div class="float-right">
	    			<button class="btn btn-primary">{{ ctrans('texts.pay_now') }}</button>
	    		</div>
	    		@endif

	    	</div>
    	</div>

{!! Former::close() !!}

    	<div class="row mt-4">
	    	<div class="col-md-12">

			<embed src="{{ asset($invoice->pdf_url()) }}#toolbar=1&navpanes=1&scrollbar=1" type="application/pdf" width="100%" height="1180px" />

			<div id="pdfCanvas" style="display:none;width:100%;background-color:#525659;border:solid 2px #9a9a9a;padding-top:40px;text-align:center">
			    <canvas id="theCanvas" style="max-width:100%;border:solid 1px #CCCCCC;"></canvas>
			</div>
		
		</div>

	</div>
</main>
</body>
@endsection