@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.view_quote'))

@section('body')
    @if(!$quote->isApproved())
        @component('portal.ninja2020.quotes.includes.actions', ['quote' => $quote])
            @section('quote-not-approved-right-side')
                <a href="{{ route('client.quote.show', $quote->hashed_id) }}?mode=portal" class="mr-4 text-primary">
                    &#8592; {{ ctrans('texts.client_portal') }}
                </a>
            @endsection
        @endcomponent
    @endif

    <iframe src="{{ $quote->pdf_file_path() }}" class="h-screen w-full border-0"></iframe>
@endsection
