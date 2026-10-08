@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.approve'))

@push('head')
    <meta name="show-quote-terms" content="{{ $settings->show_accept_quote_terms ? true : false }}">
    <meta name="require-quote-signature" content="{{ $requires_signature ? true : false }}">
    <meta name="accept-user-input" content="{{ $client->getSetting('accept_client_input_quote_approval') }}">
    <meta name="docuninja-active" content="false">

@endpush

@section('body')
    <form action="{{ route('client.quotes.bulk') }}" method="post" id="approve-form">
        @csrf
        <input type="hidden" name="action" value="approve">
        <input type="hidden" name="process" value="true">
        <input type="hidden" name="user_input" value="">

        @foreach($quotes as $quote)
            <input type="hidden" name="quotes[]" value="{{ $quote->hashed_id }}">
        @endforeach
        <input type="hidden" name="signature">
    </form>

    <div class="container mx-auto">
        <div class="grid grid-cols-6 gap-4">
            <div class="col-span-6 md:col-start-2 md:col-span-4">
                <div class="flex justify-end">
                    <div class="flex justify-end mb-2">
                        <div class="relative inline-block text-left">
                            <div>
                                <div class="rounded-md shadow-sm">
                                    <button type="button" id="approve-button"
                                            class="button button-primary bg-primary">
                                        {{ ctrans('texts.approve') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @foreach($quotes as $quote)
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-4">
                        <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                {{ ctrans('texts.quote') }}
                                <a class="button-link text-primary" href="{{ route('client.quote.show', $quote->hashed_id) }}">
                                    ({{ $quote->number }})
                                </a>
                            </h3>
                            <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500" translate>
                            </p>
                        </div>
                        <div>
                            <dl>
                                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm leading-5 font-medium text-gray-500">
                                        {{ ctrans('texts.quote_number') }}
                                    </dt>
                                    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                        {{ $quote->number }}
                                    </dd>
                                </div>
                                <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm leading-5 font-medium text-gray-500">
                                        {{ ctrans('texts.quote_date') }}
                                    </dt>
                                    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                        {{ $quote->translateDate($quote->date, $quote->client->date_format(), $quote->client->locale()) }}
                                    </dd>
                                </div>
                                <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm leading-5 font-medium text-gray-500">
                                        {{ ctrans('texts.amount') }}
                                    </dt>
                                    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                        {{ App\Utils\Number::formatMoney($quote->amount, $quote->client) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection

@section('footer')
    @include('portal.ninja2020.quotes.includes.user-input')
    @include('portal.ninja2020.invoices.includes.terms', ['entities' => $quotes, 'variables' => $variables, 'entity_type' => ctrans('texts.quote')])
    @include('portal.ninja2020.invoices.includes.signature')
@endsection

@push('footer')
    @vite('resources/js/clients/quotes/approve.js')
@endpush
