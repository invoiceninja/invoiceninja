@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.payments'))

@push('head')
    <link rel="stylesheet" href="{{ asset('js/vendor/datatables/datatables.min.css') }}">
@endpush

@section('header')
    {{ Breadcrumbs::render('payments') }}

    <div class="bg-white shadow rounded mb-4" translate>
        <div class="px-4 py-5 sm:p-6">
            <div class="sm:flex sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        {{ ctrans('texts.payments') }}
                    </h3>
                    <div class="mt-2 max-w-xl text-sm leading-5 text-gray-500">
                        <p translate>
                            {{ ctrans('texts.list_of_payments') }}
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
                class="align-middle inline-block min-w-full overflow-hidden rounded">
                <table class="min-w-full shadow rounded border border-gray-200">
                    <thead>
                    <tr>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.payment_date') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.payment_type_id') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.amount') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.transaction_reference') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.status') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($payments as $payment)
                        <tr class="cursor-pointer bg-white group hover:bg-gray-100" @click="window.location = '{{ route('client.payments.show', $payment->hashed_id) }}'">
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $payment->formatDate($payment->date, $payment->client->date_format()) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $payment->type->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ \App\Utils\Number::formatMoney($payment->amount, $payment->client) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $payment->transaction_reference }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {!! \App\Models\Payment::badgeForStatus($payment->status_id) !!}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap flex items-center justify-end text-sm leading-5 font-medium">
                                <a href="{{ route('client.payments.show', $payment->hashed_id) }}"
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
    </div>
@endsection

@push('footer')
    <script src="{{ asset('js/vendor/datatables/datatables.min.js') }}"></script>

    <script>
        $(document).ready(function() {
            $('table').DataTable();
        });
    </script>
@endpush