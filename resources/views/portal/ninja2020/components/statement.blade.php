<div>
    <div class="flex flex-col">
        <label for="status" class="block w-full">
            <span>Status</span>
            <select class="mt-2 form-select w-full">
                <option value="all">All</option>
                <option value="unpaid">Unpaid</option>
                <option value="paid">Paid</option>
            </select>
        </label> <!-- End status dropdown -->

        <div class="flex mt-4 space-x-2">
            <label for="from" class="block w-full">
                <span>From:</span>
                <input type="date" class="input w-full mt-2">
            </label>

            <label for="from" class="block w-full">
                <span>To:</span>
                <input type="date" class="input w-full mt-2">
            </label>
        </div> <!-- End date range -->

        <label for="show_payments" class="block w-full mt-4">
            <input type="checkbox" class="form-checkbox">
            <span class="ml-2">Show payments</span>
        </label> <!-- End show payments checkbox -->

        <label for="show_aging" class="block w-full mt-2">
            <input type="checkbox" class="form-checkbox">
            <span class="ml-2">Show aging</span>
        </label> <!-- End show aging checkbox -->
    </div>
</div>