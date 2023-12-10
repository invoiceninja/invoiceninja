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

            <label for="show_status" class="block w-4/5 flex items-center mr-4">
                <span class="mr-2">{{ ctrans('texts.status') }}:</span>
                <select id="status" name="status" class="input w-full form-select">
                    <option value="all">{{ ctrans('texts.all')}} </option>
                    <option value="paid">{{ ctrans('texts.paid')}} </option>
                    <option value="unpaid">{{ ctrans('texts.unpaid')}} </option>
                </select>
            </label>

            <label for="show_payments" class="block flex items-center mr-4 mt-4 md:mt-0">
                <input id="show-payments-table" type="checkbox" data-field="showPaymentsTable" class="form-checkbox" autocomplete="off">
                <span class="ml-2">{{ ctrans('texts.show_payments') }}</span>
            </label> <!-- End show payments checkbox -->

            <label for="show_aging" class="block flex items-center">
                <input id="show-aging-table" type="checkbox" data-field="showAgingTable" class="form-checkbox" autocomplete="off">
                <span class="ml-2">{{ ctrans('texts.show_aging') }}</span>
            </label> <!-- End show aging checkbox -->

        </div>
        <button onclick="setTimeout(() => this.disabled = true, 0); setTimeout(() => this.disabled = false, 5000); return true;" id="pdf-download" class="button button-primary bg-primary mt-4 md:mt-0">{{ ctrans('texts.download') }}</button>
    </div>

    @include('portal.ninja2020.components.statement-pdf-viewer', ['url' => route('client.statement.raw')])
    
@endsection

@push('footer')
    @vite('resources/js/clients/statements/view.js')
@endpush
