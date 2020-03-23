@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.quotes'))

@section('header')
    {{ Breadcrumbs::render('quotes') }}

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
                        {{ ctrans('texts.quotes') }}
                    </h3>
                    <div class="mt-2 max-w-xl text-sm leading-5 text-gray-500">
                        <p translate>
                            {{ ctrans('texts.list_of_quotes') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('body')
    <div class="flex justify-between items-center">
        <span>{{ ctrans('texts.with_selected') }}</span>
        <form action="{{ route('client.quotes.bulk') }}" method="post" id="bulkActions">
            @csrf
            <button type="submit" class="button button-primary" name="action"
                    value="download">{{ ctrans('texts.download') }}</button>
            <button type="submit" class="button button-primary" name="action"
                    value="approve">{{ ctrans('texts.approve') }}</button>
        </form>
    </div>
    <div class="flex flex-col mt-4">
        <div class="-my-2 py-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <div class="align-middle inline-block min-w-full shadow overflow-hidden rounded border-b border-gray-200">
                <table class="min-w-full">
                    <thead>
                    <tr>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            <label>
                                <input type="checkbox" class="form-check form-check-parent">
                            </label>
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.quote_number') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.quote_date') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.balance') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.valid_until') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                            {{ ctrans('texts.status') }}
                        </th>
                        <th class="px-6 py-3 border-b border-gray-200 bg-gray-50"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($quotes as $quote)
                        <tr class="bg-white group hover:bg-gray-100">
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 font-medium text-gray-900">
                                <label>
                                    <input type="checkbox" class="form-check form-check-child"
                                           data-value="{{ $quote->hashed_id }}">
                                </label>
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $quote->number }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $quote->formatDate($quote->date, $quote->client->date_format()) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ App\Utils\Number::formatMoney($quote->balance, $quote->client) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {{ $quote->formatDate($quote->date, $quote->client->date_format()) }}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap text-sm leading-5 text-gray-500">
                                {!! App\Models\Quote::badgeForStatus($quote->status_id) !!}
                            </td>
                            <td class="px-6 py-4 whitespace-no-wrap flex items-center justify-end text-sm leading-5 font-medium">
                                <a href="{{ route('client.quotes.show', $quote->hashed_id) }}"
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
        <div class="my-6">
            {{ $quotes->links('portal.ninja2020.vendor.pagination') }}
        </div>
    </div>
@endsection

@push('footer')
    <script src="{{ asset('js/clients/quotes/action-selectors.js') }}"></script>
@endpush
