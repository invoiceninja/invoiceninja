<div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
    <dt class="text-sm leading-5 font-medium text-gray-500">
        {{ ctrans('texts.subtotal') }}
    </dt>

    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        {{ App\Utils\Number::formatMoney($total['invoice_totals'], $client) }}
    </dd>

    @if($total['fee_total'] > 0)
        <dt class="text-sm leading-5 font-medium text-gray-500">
            {{ ctrans('texts.gateway_fees') }}
        </dt>

        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            {{ App\Utils\Number::formatMoney($total['fee_total'], $client) }}
        </dd>
    @endif

    @if($total['credit_totals'] > 0)
        <dt class="text-sm leading-5 font-medium text-gray-500">
            {{ ctrans('texts.credit_amount') }}
        </dt>

        <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
            {{ App\Utils\Number::formatMoney($total['credit_totals'], $client) }}
        </dd>
    @endif

    <dt class="text-sm leading-5 font-medium text-gray-500">
        {{ ctrans('texts.amount_due') }}
    </dt>
    
    <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
        {{ App\Utils\Number::formatMoney($total['amount_with_fee'], $client) }}
    </dd>
</div>
