<div>
    <livewire:billing-portal.cart.recurring-products
        :subscription="$subscription"
        :context="$context"
    />

    <livewire:billing-portal.cart.one-time-products
        :subscription="$subscription"
        :context="$context"
    />

    <livewire:billing-portal.cart.optional-recurring-products
        :subscription="$subscription"
        :context="$context"
    />

    <livewire:billing-portal.cart.optional-one-time-products
        :subscription="$subscription"
        :context="$context"
    />

    <div class="mt-3">
        <form wire:submit="handleSubmit">
            <button
                type="submit"
                class="button button-block bg-primary text-white mt-4"
            >
                {{ ctrans('texts.next') }}
            </button>
        </form>
    </div>
</div>
