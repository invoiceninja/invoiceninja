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
                        {{ ctrans('texts.recurring_invoice')}}
                    </div>
                    <div class="card-body">
                        <table class="table table-responsive-sm table-bordered">
                        	<tr><td style="text-align: right;">{{ctrans('texts.start_date')}}</td><td>{!! $invoice->formatDate($invoice->start_date,$invoice->client->date_format()) !!}</td></tr>
                        	<tr><td style="text-align: right;">{{ctrans('texts.next_send_date')}}</td><td>{!! $invoice->formatDate($invoice->next_send_date,$invoice->client->date_format()) !!}</td></tr>
                        	<tr><td style="text-align: right;">{{ctrans('texts.frequency')}}</td><td>{!! App\Models\RecurringInvoice::frequencyForKey($invoice->frequency_id) !!}</td></tr>
                        	<tr><td style="text-align: right;">{{ctrans('texts.cycles_remaining')}}</td><td>{!! $invoice->remaining_cycles !!}</td></tr>
                        	<tr><td style="text-align: right;">{{ctrans('texts.amount')}}</td><td>{!! App\Utils\Number::formatMoney($invoice->amount, $invoice->client) !!}</td></tr>

                        </table>

                        <table class="table table-responsive-sm table-sm">
                        	@foreach($invoice->invoices as $inv)
                        		{{ $inv->id }} - {{ $inv->amount }}
                        	@endforeach
                        </table>

                        @if($invoice->remaining_cycles >=1)
                        <div class="pull-right">
                            <button class="btn btn-danger mb-1" type="button" data-toggle="modal" data-target="#cancel_recurring">Request Cancellation</button>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
		</div>
    </div>
</main>

<div class="modal fade show" id="cancel_recurring" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="display: none;" aria-hidden="true">
<div class="modal-dialog modal-danger" role="document">
<div class="modal-content">
<div class="modal-header">
<h4 class="modal-title">Request Cancellation</h4>
<button class="close" type="button" data-dismiss="modal" aria-label="Close">
<span aria-hidden="true">×</span>
</button>
</div>
<div class="modal-body">
<p>Warning! You are requesting a cancellation of this service.</p>
<p>Your service may be cancelled with no further notification to you.</p>
</div>
<div class="modal-footer">
<button class="btn btn-secondary" type="button" data-dismiss="modal">Close</button>
<a href="{{ route('client.recurring_invoices.request_cancellation',['recurring_invoice' => $invoice->hashed_id]) }}" class="btn btn-danger">Confirm Cancellation</a>
</div>
</div>
</div>

</div>
</body>
@endsection
@push('css')
@endpush
@push('scripts')
@endpush
@section('footer')
@endsection

