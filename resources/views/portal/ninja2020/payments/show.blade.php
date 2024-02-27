@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.payment'))

@section('body')
    <div class="container mx-auto">
        <div class="overflow-hidden bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900">
                    {{ ctrans('texts.payment') }}
                </h3>
                <p class="max-w-2xl mt-1 text-sm leading-5 text-gray-500" translate>
                    {{ ctrans('texts.payment_details') }}
                </p>
            </div>
            <div>
                <dl>

                    @if(!empty($payment->status_id) && !is_null($payment->status_id))
                        <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium leading-5 text-gray-500">
                                {{ ctrans('texts.status') }}
                            </dt>
                            <div class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                {!! $payment->badgeForStatus() !!}
                            </div>
                        </div>
                    @endif

                    @if(!empty($payment->clientPaymentDate()) && !is_null($payment->clientPaymentDate()))
                        <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium leading-5 text-gray-500">
                                {{ ctrans('texts.payment_date') }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ $payment->clientPaymentDate() }}
                            </dd>
                        </div>
                    @endif

                    @if(!empty($payment->number) && !is_null($payment->number))
                        <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium leading-5 text-gray-500">
                                {{ ctrans('texts.number') }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ $payment->number }}
                            </dd>
                        </div>
                    @endif

                    @if(!empty($payment->transaction_reference) && !is_null($payment->transaction_reference))
                        <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium leading-5 text-gray-500">
                                {{ ctrans('texts.transaction_reference') }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                        <span class="break-all">
                            {{ $payment->transaction_reference }}
                        </span>
                            </dd>
                        </div>
                    @endif

                    
                        <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium leading-5 text-gray-500">
                                {{ ctrans('texts.method') }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ $payment->translatedType() }}
                            </dd>
                        </div>
                    

                    @if(!empty($payment->formattedAmount()) && !is_null($payment->formattedAmount()))
                        <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium leading-5 text-gray-500">
                                {{ ctrans('texts.amount') }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ $payment->formatAmount($payment->amount > 0 ? $payment->amount : $payment?->invoices->sum('pivot.amount')) }}
                            </dd>
                        </div>
                    @endif

                    @if(!empty($payment->status_id) && !is_null($payment->status_id))

                        @if($payment->refunded > 0)
                            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium leading-5 text-gray-500">
                                    {{ ctrans('texts.refunded') }}
                                </dt>
                                <div class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    {{ \App\Utils\Number::formatMoney($payment->refunded, $payment->client) }}
                                </div>
                            </div>
                        @endif

                        @if($payment->applied != $payment->amount)
                            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium leading-5 text-gray-500">
                                    {{ ctrans('texts.remaining') }}
                                </dt>
                                <div class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    {{ \App\Utils\Number::formatMoney($payment->amount - $payment->applied, $payment->client) }}
                                </div>
                            </div>
                        @endif

                    @endif
                </dl>
            </div>
        </div>
        <div class="mt-4 overflow-hidden bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900">
                    {{ ctrans('texts.invoices') }}
                </h3>
                <p class="max-w-2xl mt-1 text-sm leading-5 text-gray-500">
                    {{ ctrans('texts.list_of_payment_invoices') }}
                </p>
            </div>
            <div>
                <dl>
                    @foreach($payment->invoices as $invoice)
                        @if(!$invoice->is_proforma && !$invoice->is_deleted)
                        <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium leading-5 text-gray-500">
                                {{ ctrans('texts.invoice_number') }}
                            </dt>
                            <div class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                <a class="button-link text-primary"
                                   href="{{ route('client.invoice.show', ['invoice' => $invoice->hashed_id])}}">
                                    {{ $invoice->number }}
                                </a>
                                - {{ \App\Utils\Number::formatMoney($payment->invoices->where('id', $invoice->id)->sum('pivot.amount') - $payment->invoices->where('id', $invoice->id)->sum('pivot.refunded'), $payment->client) }}
                            </div>
                        </div>
                        @endif
                    @endforeach
                </dl>
            </div>
            
                <!-- if this is a stripe bank transfer - show the required details here: -->
        </div>
            @if($bank_details)
                    @include('portal.ninja2020.gateways.stripe.bank_transfer.bank_details')
                @endif
            
    </div>
@endsection
