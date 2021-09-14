<div>
    <div class="flex flex-col md:flex-row md:items-center">
        <label for="status" class="flex items-center mr-4">
            <span class="mr-2">{{ ctrans('texts.status') }}</span>
            <select class="input">
                <option value="all">{{ ctrans('texts.all') }}</option>
                <option value="unpaid">{{ ctrans('texts.unpaid') }}</option>
                <option value="paid">{{ ctrans('texts.paid') }}</option>
            </select>
        </label> <!-- End status dropdown -->

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
            <input type="checkbox" class="form-checkbox">
            <span class="ml-2">{{ ctrans('texts.show_payments') }}</span>
        </label> <!-- End show payments checkbox -->

        <label for="show_aging" class="block flex items-center">
            <input type="checkbox" class="form-checkbox">
            <span class="ml-2">{{ ctrans('texts.show_aging') }}</span>
        </label> <!-- End show aging checkbox -->
    </div>
</div>