@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.credits'))

@push('head')
    <link rel="stylesheet" href="{{ asset('js/vendor/datatables/datatables.min.css') }}">
@endpush

@section('header')
    {{ Breadcrumbs::render('credits') }}

    @if($errors->any())
        <div class="alert alert-failure mb-4">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="bg-white shadow rounded mb-4" translate>
        <div class="px-4 py-5 sm:p-6">
            <div class="sm:flex sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        {{ ctrans('texts.credits') }}
                    </h3>
                    <div class="mt-2 max-w-xl text-sm leading-5 text-gray-500">
                        <p translate>
                            {{ ctrans('texts.list_of_credits') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('body')
    <div class="flex flex-col mt-4">
        <div class="-my-2 py-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <div
                class="align-middle inline-block min-w-full overflow-hidden rounded">
                <table class="min-w-full shadow rounded border border-gray-200">
                    <thead>
                    <tr>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.amount') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.balance') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.credit_date') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.public_notes') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($credits as $credit)
                        <tr class="bg-white group hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ App\Utils\Number::formatMoney($credit->amount, $credit->client) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ App\Utils\Number::formatMoney($credit->balance, $credit->client) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $credit->formatDate($credit->date, $credit->client->date_format()) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ empty($credit->public_notes) ? '/' : $credit->public_notes }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                <a href="{{ route('client.credits.show', $credit->hashed_id) }}"
                                   class="button-link">
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
    <script src="{{ asset('js/clients/quotes/action-selectors.js') }}"></script>
    <script src="{{ asset('js/vendor/datatables/datatables.min.js') }}"></script>

    <script>
        $(document).ready(function() {
            $('table').DataTable();
        });
    </script>
@endpush
