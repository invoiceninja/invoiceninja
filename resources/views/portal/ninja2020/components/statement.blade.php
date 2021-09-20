<div>
    <div class="flex flex-col md:flex-row md:justify-between">
        <div class="flex flex-col md:flex-row md:items-center">
            {{-- <label for="status" class="flex items-center mr-4">
                <span class="mr-2">{{ ctrans('texts.status') }}</span>
                <select class="input">
                    <option value="all">{{ ctrans('texts.all') }}</option>
                    <option value="unpaid">{{ ctrans('texts.unpaid') }}</option>
                    <option value="paid">{{ ctrans('texts.paid') }}</option>
                </select>
            </label> <!-- End status dropdown --> --}}

            <div class="flex">
                <label for="from" class="block w-full flex items-center mr-4">
                    <span class="mr-2">{{ ctrans('texts.from') }}:</span>
                    <input wire:model="options.start_date" type="date" class="input w-full">
                </label>

                <label for="to" class="block w-full flex items-center mr-4">
                    <span class="mr-2">{{ ctrans('texts.to') }}:</span>
                    <input wire:model="options.end_date" type="date" class="input w-full">
                </label>
            </div> <!-- End date range -->

            <label for="show_payments" class="block flex items-center mr-4 mt-4 md:mt-0">
                <input wire:model="options.show_payments_table" type="checkbox" class="form-checkbox" autocomplete="off">
                <span class="ml-2">{{ ctrans('texts.show_payments') }}</span>
            </label> <!-- End show payments checkbox -->

            <label for="show_aging" class="block flex items-center">
                <input wire:model="options.show_aging_table" type="checkbox" class="form-checkbox" autocomplete="off">
                <span class="ml-2">{{ ctrans('texts.show_aging') }}</span>
            </label> <!-- End show aging checkbox -->
        </div>
        <button wire:click="download" class="button button-primary bg-primary mt-4 md:mt-0">{{ ctrans('texts.download') }}</button>
    </div>

    @include('portal.ninja2020.components.pdf-viewer', ['url' => $url])
</div>
