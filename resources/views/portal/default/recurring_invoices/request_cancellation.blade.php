@extends('portal.default.layouts.master')

@section('body')
    <main class="main">
        <div class="container-fluid">
            <div class="row" style="padding-top: 30px;">
                <div class="col d-flex justify-content-center">
                    <div class="card w-50 p-10">
                        <div class="card-header">
                            {{ ctrans('texts.cancellation') }}
                        </div>
                        <div class="card-body">
                            <table class="table table-responsive-sm table-bordered">
                                <tr>
                                    <td style="text-align: right;">{{ctrans('texts.start_date')}}</td>
                                    <td>{!! $invoice->start_date !!}</td>
                                </tr>
                                <tr>
                                    <td style="text-align: right;">{{ctrans('texts.next_send_date')}}</td>
                                    <td>{!! $invoice->next_send_date !!}</td>
                                </tr>
                                <tr>
                                    <td style="text-align: right;">{{ctrans('texts.frequency')}}</td>
                                    <td>{!! App\Models\RecurringInvoice::frequencyForKey($invoice->frequency_id) !!}</td>
                                </tr>
                                <tr>
                                    <td style="text-align: right;">{{ctrans('texts.cycles_remaining')}}</td>
                                    <td>{!! $invoice->remaining_cycles !!}</td>
                                </tr>
                                <tr>
                                    <td style="text-align: right;">{{ctrans('texts.amount')}}</td>
                                    <td>{!! $invoice->amount !!}</td>
                                </tr>

                            </table>
                            <div class="alert alert-primary" role="alert">{{ ctrans('texts.cancellation_pending') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection