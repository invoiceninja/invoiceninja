@extends('portal.ninja2020.layout.app')

@section('meta_title', ctrans('texts.statement'))

@push('head')
    <meta name="pdf-url" content="{{ route('client.statement.raw') }}">
@endpush

@section('body')
    <div class="flex flex-col md:flex-row md:justify-between">
        <div class="flex flex-col md:flex-row md:items-center">
            <div class="flex">
                <label for="from" class="block w-full flex items-center mr-4">
                    <span class="mr-2">{{ ctrans('texts.from') }}:</span>
                    <input type="date" class="input w-full">
                </label>

                <label for="to" class="block w-full flex items-center mr-4">
                    <span class="mr-2">{{ ctrans('texts.to') }}:</span>
                    <input type="date" class="input w-full">
                </label>
            </div> <!-- End date range -->

            <label for="show_payments" class="block flex items-center mr-4 mt-4 md:mt-0">
                <input type="checkbox" class="form-checkbox" autocomplete="off">
                <span class="ml-2">{{ ctrans('texts.show_payments') }}</span>
            </label> <!-- End show payments checkbox -->

            <label for="show_aging" class="block flex items-center">
                <input type="checkbox" class="form-checkbox" autocomplete="off">
                <span class="ml-2">{{ ctrans('texts.show_aging') }}</span>
            </label> <!-- End show aging checkbox -->
        </div>
        <button class="button button-primary bg-primary mt-4 md:mt-0">{{ ctrans('texts.download') }}</button>
    </div>

    @include('portal.ninja2020.components.pdf-viewer', ['url' => route('client.statement.raw')])
@endsection

@push('footer')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // document
            //     .querySelector('meta[name=pdf-url]')
            //     .content = 'https://google.com';

            // document.querySelector('meta[name=pdf-url]').dispatchEvent(new Event('change'));
        });
    </script>
@endpush