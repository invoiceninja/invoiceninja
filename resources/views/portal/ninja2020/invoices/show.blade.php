@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.view_invoice'))

@push('head')
    <meta name="pdf-url" content="{{ asset($invoice->pdf_url()) }}">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.4.456/pdf.min.js" integrity="sha256-O6polm3ZRTZEOAneYbvsKty3c3KRcDf20McwtlCga5s=" crossorigin="anonymous"></script>
@endpush

@section('header')
    {{ Breadcrumbs::render('invoices.show', $invoice) }}
@endsection

@section('body')

    @if($invoice->isPayable())
        <form action="{{ route('client.invoices.bulk') }}" method="post">
            @csrf
            <div class="bg-white shadow sm:rounded-lg mb-4" translate>
                <div class="px-4 py-5 sm:p-6">
                    <div class="sm:flex sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                {{ ctrans('texts.unpaid') }}
                            </h3>
                            <div class="mt-2 max-w-xl text-sm leading-5 text-gray-500">
                                <p translate>
                                    {{ ctrans('texts.invoice_still_unpaid') }}
                                    <!-- This invoice is still not paid. Click the button to complete the payment. -->
                                </p>
                            </div>
                        </div>
                        <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex-shrink-0 sm:flex sm:items-center">
                            <div class="inline-flex rounded-md shadow-sm">
                                <input type="hidden" name="invoices[]" value="{{ $invoice->hashed_id }}">
                                <input type="hidden" name="action" value="payment">
                                <button class="button button-primary">@lang('texts.pay_now')</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @endif

    <div class="flex items-center justify-between">
        <button class="input-label" id="previous-page-button">Previous page</button>
        <button class="input-label" id="next-page-button">Next page</button>
    </div> 

    <canvas id="pdf-placeholder" class="shadow rounded bg-white mt-4"></canvas>

    <!-- <embed src="{{ asset($invoice->pdf_url()) }}#toolbar=1&navpanes=1&scrollbar=1" type="application/pdf" width="100%"
           height="1180px"/> -->

@endsection

@section('footer')
    <script src="{{ asset('js/clients/shared/pdf.js') }}"></script>

    <script>
        // let url = document.querySelector("meta[name='pdf-url'").content;
        // let canvas = document.getElementById("pdf-placeholder");

        // function renderInvoicePDF(selected_page) {
        //     const pdf = pdfjsLib.getDocument(url).promise.then((pdf) => {

        //         const context = canvas.getContext("2d");

        //         let page = pdf.getPage(selected_page).then((p) => {

        //             const viewport = p.getViewport({ scale: 1 });
        //             canvas.height = viewport.height;
        //             canvas.width = viewport.width;      
                    
        //             p.render({
        //                 canvasContext: context,
        //                 viewport,
        //             })
        //         });
        //     });
        // }

        // renderInvoicePDF(1);

        // (async () => {
        //     const pdf = await pdfjsLib.getDocument(pdfUrl).promise;

        //     const page = await pdf.getPage(1);

        //     const context = canvas.getContext("2d");

        //     const viewport = page.getViewport({ scale: 1 });

        //     canvas.height = viewport.height;
        //     canvas.width = viewport.width;

        //     console.log(viewport);

        //     page.render({
        //         canvasContext: context,
        //         viewport: viewport 
        //     });

        // })();
    </script>
@endsection
