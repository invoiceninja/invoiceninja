@extends('portal.ninja2020.layout.app')

@section('body')
    <div class="flex flex-col">
        <div class="-my-2 py-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <div
                class="align-middle inline-block min-w-full shadow overflow-hidden rounded border-b border-gray-200">
                <table class="min-w-full">
                    <thead>
                    <tr>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            <label>
                                <input type="checkbox" class="form-check form-check-parent"
                                       @click="document.querySelectorAll('.form-check-child').checked = true;">
                            </label>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            @lang('texts.invoice_number')
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            @lang('texts.invoice_date')
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            @lang('texts.balance')
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            @lang('texts.due_date')
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            @lang('texts.status')
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($invoices as $invoice)
                        <tr class="bg-white group hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 font-medium text-gray-900">
                                <label>
                                    <input type="checkbox" class="form-check form-check-child">
                                </label>
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $invoice->number }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $invoice->date->format($invoice->client->date_format()) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ App\Utils\Number::formatMoney($invoice->balance, $invoice->client) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $invoice->date->format($invoice->client->date_format()) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {!! App\Models\Invoice::badgeForStatus($invoice->status) !!}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap flex items-center justify-end text-sm leading-5 font-medium">
                                @if($invoice->isPayable())
                                    <button class="button button-primary py-1 px-2 text-xs uppercase mr-3">
                                        @lang('texts.pay_now')
                                    </button>
                                @endif
                                <a href="{{ route('client.invoice.show', $invoice->hashed_id) }}"
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

@push('footer')
    <script>
        let parentElement = document.querySelector(".form-check-parent");
        parentElement.addEventListener("click", function () {
            document.querySelectorAll(".form-check-child").forEach(function (child) {
                child.checked = parentElement.checked;
            });
        });
    </script>
@endpush
