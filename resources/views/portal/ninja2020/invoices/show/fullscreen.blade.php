@extends('portal.ninja2020.layout.clean', ['custom_body_class' => 'overflow-y-hidden'])
@section('meta_title', ctrans('texts.view_invoice'))

@section('body')
    @if($invoice->isPayable())
        <form action="{{ route('client.invoices.bulk') }}" method="post">
            @csrf
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="sm:flex sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                {{ ctrans('texts.invoice_number_placeholder', ['invoice' => $invoice->number])}}
                                - {{ ctrans('texts.unpaid') }}
                            </h3>
                            <div class="mt-2 max-w-xl text-sm leading-5 text-gray-500">
                                <p translate>
                                {{ ctrans('texts.invoice_still_unpaid') }}
                                <!-- This invoice is still not paid. Click the button to complete the payment. -->
                                </p>
                            </div>
                        </div>
                        <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex-shrink-0 sm:flex sm:items-center">
                            <a href="{{ route('client.invoice.show', $invoice->hashed_id) }}?mode=portal"
                               class="mr-4 text-primary">
                                &#8592; {{ ctrans('texts.client_portal') }}
                            </a>

                            <div class="inline-flex rounded-md shadow-sm">
                                <input type="hidden" name="invoices[]" value="{{ $invoice->hashed_id }}">
                                <input type="hidden" name="action" value="payment">
                                <button class="button button-primary bg-primary">{{ ctrans('texts.pay_now') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @else
        <div class="bg-white shadow sm:rounded-lg mb-4">
            <div class="px-4 py-5 sm:p-6">
                <div class="sm:flex sm:items-start sm:justify-between">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        {{ ctrans('texts.invoice_number_placeholder', ['invoice' => $invoice->number])}}
                        - {{ ctrans('texts.paid') }}
                    </h3>
                    <a href="{{ route('client.invoice.show', $invoice->hashed_id) }}?mode=portal"
                       class="mr-4 text-primary">
                        &#8592; {{ ctrans('texts.client_portal') }}
                    </a>
                </div>
            </div>
        </div>
    @endif

    <iframe src="{{ $invoice->pdf_file_path() }}" class="h-screen w-full border-0"></iframe>
@endsection
