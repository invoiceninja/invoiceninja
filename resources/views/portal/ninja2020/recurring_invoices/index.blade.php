@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.recurring_invoices'))

@section('header')
    {{ Breadcrumbs::render('recurring_invoices') }}

    <div class="bg-white shadow rounded mb-4" translate>
        <div class="px-4 py-5 sm:p-6">
            <div class="sm:flex sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        {{ ctrans('texts.recurring_invoices') }}
                    </h3>
                    <div class="mt-2 max-w-xl text-sm leading-5 text-gray-500">
                        <p>
                            {{ ctrans('texts.list_of_recurring_invoices') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('body')
    <div class="flex flex-col">
        <div class="-my-2 py-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <div
                class="align-middle inline-block min-w-full shadow overflow-hidden rounded border-b border-gray-200">
                <table class="min-w-full">
                    <thead>
                    <tr>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.frequency') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.start_date') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.next_send_date') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.cycles_remaining') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.amount') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($invoices as $invoice)
                        <tr class="bg-white group hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ \App\Models\RecurringInvoice::frequencyForKey($invoice->frequency_id) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $invoice->formatDate($invoice->date, $invoice->client->date_format()) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $invoice->formatDate($invoice->next_send_date, $invoice->client->date_format()) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $invoice->remaining_cycles }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ \App\Utils\Number::formatMoney($invoice->amount, $invoice->client) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap flex items-center justify-end text-sm leading-5 font-medium">
                                <a href="{{ route('client.recurring_invoices.show', $invoice->hashed_id) }}"
                                   class="text-blue-600 hover:text-indigo-900 focus:outline-none focus:underline">
                                    @lang('texts.view')
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="my-6">
            {{ $invoices->links('portal.ninja2020.vendor.pagination') }}
        </div>
    </div>
@endsection
