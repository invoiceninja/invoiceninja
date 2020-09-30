@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.view_quote'))

@section('body')
    @if(!$quote->isApproved())
        <form action="{{ route('client.quotes.bulk') }}" method="post">
            @csrf
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="quotes[]" value="{{ $quote->hashed_id }}">
            <div class="bg-white" translate>
                <div class="px-4 py-5 sm:p-6">
                    <div class="sm:flex sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900" translate>
                                {{ ctrans('texts.waiting_for_approval') }}
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
                                <button class="button button-primary bg-primary">{{ ctrans('texts.approve') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @endif

    <iframe src="{{ $quote->pdf_file_path() }}" class="h-screen w-full border-0"></iframe>
@endsection