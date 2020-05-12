@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.payment'))

@section('header')
    {{ Breadcrumbs::render('payments.show', $payment) }}
@endsection

@section('body')
    <div class="container mx-auto">
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    {{ ctrans('texts.payment') }}
                </h3>
                <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500" translate>
                    {{ ctrans('texts.payment_details') }}
                </p>
            </div>
            <div>
                <dl>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.payment_date') }}
                        </dt>
                        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ $payment->clientPaymentDate() }}
                        </dd>
                    </div>
                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.transaction_reference') }}
                        </dt>
                        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ $payment->transaction_reference }}
                        </dd>
                    </div>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.method') }}
                        </dt>
                        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ $payment->type->name }}
                        </dd>
                    </div>
                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.amount') }}
                        </dt>
                        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ $payment->formattedAmount() }}
                        </dd>
                    </div>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.status') }}
                        </dt>
                        <div class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {!! \App\Models\Payment::badgeForStatus($payment->status_id) !!}
                        </div>
                    </div>
                </dl>
            </div>
        </div>
        <div class="bg-white shadow overflow-hidden sm:rounded-lg mt-4">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    {{ ctrans('texts.invoices') }}
                </h3>
                <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500" translate>
                    {{ ctrans('texts.list_of_payment_invoices') }}
                </p>
            </div>
            <div>
                <dl>
                    @foreach($payment->invoices as $invoice)
                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm leading-5 font-medium text-gray-500">
                                {{ ctrans('texts.invoice_number') }}
                            </dt>
                            <div class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                <a class="button-link"
                                   href="{{ route('client.invoice.show', ['invoice' => $invoice->hashed_id])}}">
                                    {{ $invoice->number }}
                                </a>
                                <span>Payment: {{ $payment->hashed_id }} Invoice: {{ $invoice->hashed_id }} Amount: {{ $payment->amount }}</span>
                            </div>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    </div>
@endsection
