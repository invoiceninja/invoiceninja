@extends('portal.ninja2020.layout.app')

@section('body')
    <div class="container mx-auto">
        <div class="bg-white shadow overflow-hidden rounded">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    {{ ctrans('texts.recurring_invoices') }}
                </h3>
                <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500" translate>
                    Details of the recurring invoice.
                </p>
            </div>
            <div>
                <dl>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.start_date') }}
                        </dt>
                        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ $invoice->translateDate($invoice->start_date, $invoice->client->date_format(), $invoice->client->locale()) }}
                        </dd>
                    </div>
                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.next_send_date') }}
                        </dt>
                        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ $invoice->translateDate($invoice->next_send_date, $invoice->client->date_format(), $invoice->client->locale()) }}
                        </dd>
                    </div>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.frequency') }}
                        </dt>
                        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ \App\Models\RecurringInvoice::frequencyForKey($invoice->frequency_id) }}
                        </dd>
                    </div>
                    <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.cycles_remaining') }}
                        </dt>
                        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ $invoice->remaining_cycles == '-1' ? ctrans('texts.endless') : $invoice->remaining_cycles }}
                            @if($invoice->remaining_cycles == '-1') &#8734; @endif
                        </dd>
                    </div>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm leading-5 font-medium text-gray-500">
                            {{ ctrans('texts.amount') }}
                        </dt>
                        <div class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ \App\Utils\Number::formatMoney($invoice->amount, $invoice->client) }}
                        </div>
                    </div>
            </div>
        </div>
        <div class="bg-white shadow rounded-sm mb-4 mt-4 border-l-2 border-green-500" translate>
            <div class="px-4 py-5 sm:p-6">
                <div class="sm:flex sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Cancellation pending, we'll be in touch!
                        </h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
