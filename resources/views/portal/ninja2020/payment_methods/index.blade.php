@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.payment_methods'))

@push('head')
    <link rel="stylesheet" href="{{ asset('js/vendor/datatables/datatables.min.css') }}">
@endpush

@section('header')
    {{ Breadcrumbs::render('payment_methods') }}

    <div class="bg-white shadow rounded mb-4" translate>
        <div class="px-4 py-5 sm:p-6">
            <div class="sm:flex sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        {{ ctrans('texts.payment_methods') }}
                    </h3>
                    <div class="mt-2 max-w-xl text-sm leading-5 text-gray-500">
                        <p translate>
                            {{ ctrans('texts.list_of_payment_methods') }}
                        </p>
                    </div>
                </div>
                <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex-shrink-0 sm:flex sm:items-center">
                    <div class="inline-flex rounded-md shadow-sm">
                        <input type="hidden" name="hashed_ids">
                        <input type="hidden" name="action" value="payment">
                        @if(auth()->user()->client->getCreditCardGateway())
                            <a href="{{ route('client.payment_methods.create') }}" class="button button-primary">@lang('texts.add_payment_method')</a>
                        @endif
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
                            {{ ctrans('texts.created_at') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.payment_type_id') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.type') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.expires') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.card_number') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.default') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($payment_methods as $payment_method)
                        <tr class="bg-white group hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $payment_method->formatDateTimestamp($payment_method->created_at, auth()->user()->client->date_format()) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ ctrans("texts.{$payment_method->gateway_type->alias}") }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ ucfirst(optional($payment_method->meta)->brand) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                @if(isset($payment_method->meta->exp_month) && isset($payment_method->meta->exp_year))
                                    {{ $payment_method->meta->exp_month}} / {{ $payment_method->meta->exp_year }}
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                @isset($payment_method->meta->last4)
                                    **** {{ $payment_method->meta->last4 }}
                                @endisset
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                @if($payment_method->is_default)
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                         stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                         stroke-linejoin="round" class="feather feather-check">
                                        <path d="M20 6L9 17l-5-5"/>
                                    </svg>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap flex items-center justify-end text-sm leading-5 font-medium">
                                <a href="{{ route('client.payment_methods.show', $payment_method->hashed_id) }}"
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
            {{ $payment_methods->links('portal.ninja2020.vendor.pagination') }}
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