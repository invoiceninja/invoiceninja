@extends('portal.default.layouts.master')
@section('header')

@stop
@section('body')
<main class="main">
    <div class="container-fluid">
		<div class="row" style="padding-top: 30px;">
            <div class="col d-flex justify-content-center">
                <div class="card w-50 p-10">
                    <div class="card-header">
                        {{ ctrans('texts.payment')}}
                    </div>
                    <div class="card-body">
                        <table class="table table-responsive-sm table-bordered">
                        	<tr><td style="text-align: right;">{{ctrans('texts.payment_date')}}</td><td>{{$payment->payment_date}}</td></tr>
                        	<tr><td style="text-align: right;">{{ctrans('texts.transaction_reference')}}</td><td>{{$payment->transaction_reference}}</td></tr>
                        	<tr><td style="text-align: right;">{{ctrans('texts.method')}}</td><td>{{$payment->type->name}}</td></tr>
                        	<tr><td style="text-align: right;">{{ctrans('texts.amount')}}</td><td>{{$payment->formattedAmount()}}</td></tr>
                        	<tr><td style="text-align: right;">{{ctrans('texts.status')}}</td><td>{!! $payment::badgeForStatus($payment->status_id) !!}</td></tr>
                        </table>

                        <table class="table table-responsive-sm table-sm">
                        	@foreach($payment->invoices as $invoice)
                        		<tr><td style="text-align: right;">{{ ctrans('texts.invoice_number')}}</td><td><a href="{{ route('client.invoice.show', ['invoice' => $invoice->hashed_id])}}">{{ $invoice->invoice_number }}</a></td></tr>
                        	@endforeach
                        </table>
                    </div>
                </div>
            </div>
		</div>
    </div>
</main>

</body>
@endsection
@push('css')
@endpush
@push('scripts')
@endpush
@section('footer')
@endsection

