@extends('portal.ninja2020.layout.app')

@section('meta_title', ctrans('texts.processing'))

@section('body')
    <form method="post" action="{{ route('client.quotes.bulk') }}" id="continue-quote-approval">
        @csrf
        <input type="hidden" name="request_hash" value="{{ $request_hash }}">

        <button type="submit" class="button button-primary bg-primary">
            {{ ctrans('texts.continue') }}
        </button>
    </form>
@endsection

@push('footer')
    <script>
        document.getElementById('continue-quote-approval').submit();
    </script>
@endpush
