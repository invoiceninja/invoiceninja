@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.view_quote'))

@section('header')
    {{ Breadcrumbs::render('quotes.show', $quote) }}
@endsection

@section('body')

    @if(!$quote->isApproved())
        <form action="{{ route('client.quotes.bulk') }}" method="post">
            @csrf
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="quotes[]" value="{{ $quote->hashed_id }}">
            <div class="bg-white shadow sm:rounded-lg mb-4" translate>
                <div class="px-4 py-5 sm:p-6">
                    <div class="sm:flex sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900" translate>
                                {{ ctrans('texts.waitin_for_approval') }}
                            </h3>
                            <div class="mt-2 max-w-xl text-sm leading-5 text-gray-500">
                                <p translate>
                                    {{ ctrans('texts.quote_still_not_approved') }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex-shrink-0 sm:flex sm:items-center">
                            <div class="inline-flex rounded-md shadow-sm">
                                <input type="hidden" name="action" value="payment">
                                <button class="button button-primary">@lang('texts.approve')</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @endif

    <embed src="{{ asset($quote->pdf_file_path()) }}#toolbar=1&navpanes=1&scrollbar=1" type="application/pdf"
           width="100%"
           height="1180px"/>

@endsection
