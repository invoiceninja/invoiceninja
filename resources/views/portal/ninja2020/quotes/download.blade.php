@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.view_quote'))

@push('head')

@endpush

@section('body')

    <div class="container mx-auto">
        <div class="grid grid-cols-6 gap-4">
            <div class="flex float-right">
                <form action="{{ route('client.quotes.download') }}" method="post" id="bulkActions">
                    @foreach($quotes as $quote)
                        <input type="hidden" name="quotes[]" value="{{ $quote->hashed_id }}">
                    @endforeach
                    @csrf
                    <button type="submit" onclick="setTimeout(() => this.disabled = true, 0); setTimeout(() => this.disabled = true, 5000); return true;" class="button button-primary bg-primary" name="action" value="download">{{ ctrans('texts.download') }}</button>
                </form>
            </div>
        </div>

        @foreach($quotes as $quote)
        <div>
            <dl>
                @if(!empty($quote->number) && !is_null($quote->number))
                <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium leading-5 text-gray-500">
                        {{ ctrans('texts.quote_number') }}
                    </dt>
                    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                        {{ $quote->number }}
                    </dd>
                </div>
                @endif
            </dl>
        </div>
    
    @endforeach

    </div>

@endsection

@section('footer')
@endsection
