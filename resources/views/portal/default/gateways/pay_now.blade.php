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
                        <div class="list-group">
                            @foreach($invoices as $invoice)
                                <a class="list-group-item list-group-item-action flex-column align-items-start" href="javascript:void(0);">
                                    <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mr-4"># {{ $invoice->number }}</h5>
                                    <small>{{ $invoice->due_date }}</small>
                                    </div>
                                <p class="mb-1 pull-right">{{ $invoice->balance }}</p>
                                <small>
                                    @if($invoice->po_number)
                                    {{ $invoice->po_number }}
                                    @elseif($invoice->public_notes)
                                    {{ $invoice->public_notes }}
                                    @else
                                    {{ $invoice->invoice_date}}
                                    @endif
                                </small>
                                </a>
                            @endforeach
                        </div>

                        <div class="py-md-5">
                            <ul class="list-group">
                                <li class="list-group-item d-flex list-group-item-action justify-content-between align-items-center"><strong>{{ ctrans('texts.total')}}</strong>
                                    <h3><span class="badge badge-primary badge-pill"><strong>{{ $amount }}</strong></span></h3>
                                </li>
                                @if($credit_totals > 0)
                                <li class="list-group-item d-flex list-group-item-action justify-content-between align-items-center"><strong>{{ ctrans('texts.credit_amount')}}</strong>
                                    <h3><span class="badge badge-primary badge-pill"><strong>{{ $credit_totals }}</strong></span></h3>
                                </li>
                                @endifs
                                @if($fee > 0)
                                <li class="list-group-item d-flex list-group-item-action justify-content-between align-items-center"><strong>{{ ctrans('texts.gateway_fee')}}</strong>
                                    <h3><span class="badge badge-primary badge-pill"><strong>{{ $fee }}</strong></span></h3>
                                </li>
                                <li class="list-group-item d-flex list-group-item-action justify-content-between align-items-center"><strong>{{ ctrans('texts.amount_due')}}</strong>
                                    <h3><span class="badge badge-primary badge-pill"><strong>{{ $amount_with_fee }}</strong></span></h3>
                                </li>
                                @endif
                            </ul>
                        </div>

                        @yield('pay_now')
                        
                    </div>
                </div>
            </div>
		</div>
    </div>
</main>
</body>
@endsection
@push('scripts')
@endpush
@section('footer')
@endsection

