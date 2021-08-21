@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.entity_number_placeholder', ['entity' => ctrans('texts.credit'), 'entity_number' => $credit->number]))

@push('head')
    <meta name="pdf-url" content="{{ $credit->pdf_file_path(null, 'url', true) }}">
    <script src="{{ asset('js/vendor/pdf.js/pdf.min.js') }}"></script>
@endpush

@section('body')
    @include('portal.ninja2020.components.entity-documents', ['entity' => $credit])

    @include('portal.ninja2020.components.pdf-viewer', ['entity' => $credit])

    <div class="flex justify-center">
        <canvas id="pdf-placeholder" class="shadow rounded-lg bg-white lg:hidden mt-4 p-4"></canvas>
    </div>
@endsection

@section('footer')
    <script src="{{ asset('js/clients/shared/pdf.js') }}"></script>
@endsection
