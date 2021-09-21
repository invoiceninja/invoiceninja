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
                    <input id="date-from" type="date" class="input w-full" data-field="startDate" value="{{ now()->startOfYear()->format('Y-m-d') }}">
                </label>

                <label for="to" class="block w-full flex items-center mr-4">
                    <span class="mr-2">{{ ctrans('texts.to') }}:</span>
                    <input id="date-to" type="date" class="input w-full" data-field="endDate" value="{{ now()->format('Y-m-d') }}">
                </label>
            </div> <!-- End date range -->

            <label for="show_payments" class="block flex items-center mr-4 mt-4 md:mt-0">
                <input id="show-payments-table" type="checkbox" data-field="showPaymentsTable" class="form-checkbox" autocomplete="off">
                <span class="ml-2">{{ ctrans('texts.show_payments') }}</span>
            </label> <!-- End show payments checkbox -->

            <label for="show_aging" class="block flex items-center">
                <input id="show-aging-table" type="checkbox" data-field="showAgingTable" class="form-checkbox" autocomplete="off">
                <span class="ml-2">{{ ctrans('texts.show_aging') }}</span>
            </label> <!-- End show aging checkbox -->
        </div>
        <button class="button button-primary bg-primary mt-4 md:mt-0">{{ ctrans('texts.download') }}</button>
    </div>

    @include('portal.ninja2020.components.pdf-viewer', ['url' => route('client.statement.raw')])
@endsection

@push('footer')
    <script>
        class Statement {
            constructor() {
                this.url = new URL(document.querySelector('meta[name=pdf-url]').content);
                this.startDate = '';
                this.endDate = '';
                this.showPaymentsTable = false;
                this.showAgingTable = false;
            }

            bindEventListeners() {
                ['#date-from', '#date-to', '#show-payments-table', '#show-aging-table'].forEach(selector => {
                    document
                        .querySelector(selector)
                        .addEventListener('change', (event) => this.handleValueChange(event));
                });
            }

            handleValueChange(event) {
                if (event.target.type === 'checkbox') {
                    console.log(1);
                    this[event.target.dataset.field] = event.target.checked;
                } else {
                    this[event.target.dataset.field] = event.target.value;
                }

                this.updatePdf();
            }

            get composedUrl() {
                this.url.search = '';

                if (this.startDate.length > 0) {
                    this.url.searchParams.append('start_date', this.startDate);
                }

                if (this.endDate.length > 0) {
                    this.url.searchParams.append('end_date', this.endDate);
                }

                this.url.searchParams.append('show_payments_table', +this.showPaymentsTable);
                this.url.searchParams.append('show_aging_table', +this.showAgingTable);

                return this.url.href;
            }

            updatePdf() {
                document
                    .querySelector('meta[name=pdf-url]')
                    .content = this.composedUrl;

                let iframe = document.querySelector('#pdf-iframe');

                if (iframe) {
                    iframe.src = this.composedUrl;
                }

                document.querySelector('meta[name=pdf-url]').dispatchEvent(new Event('change'));
            }

            handle() {
                this.bindEventListeners();
            }
        }

        new Statement().handle();
    </script>
@endpush