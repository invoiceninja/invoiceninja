<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4" id="rotessa-bank-transfer">
    @if (count($tokens) > 0)
        <div class="alert alert-failure mb-4" hidden id="errors"></div>

        @include('portal.ninja2020.gateways.includes.payment_details')

        <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
            @csrf
            <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
            <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
            <input type="hidden" name="source" value="">
            <input type="hidden" name="amount" value="{{ $amount }}">
            <input type="hidden" name="currency" value="{{ $currency }}">
            <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
            <input type="hidden" name="frequency" value="Once">
            <input type="hidden" name="installments" value="1">

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
            @if (count($tokens) > 0)
                @foreach ($tokens as $token)
                    <label class="mr-4">
                        <input type="radio" data-token="{{ $token->token }}" name="payment-type"
                            class="form-radio cursor-pointer toggle-payment-with-token" />
                        <span class="ml-1 cursor-pointer">
                            {{ App\Models\GatewayType::getAlias($token->gateway_type_id) }} ({{ $token->meta->brand ?? 'Bank Transfer' }})
                             &nbsp; {{ ctrans('texts.account_number') }}#: {{ $token->meta?->last4 ?? '' }}
                        </span>
                    </label><br/>
                @endforeach
            @endisset
            <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                {{ ctrans('texts.process_date') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                <input autocomplete="new-password" readonly type="date" min="{{ $due_date }}" name="process_date" id="process_date" required class="input w-full" placeholder="" value="{{ old('process_date', $process_date ) }}">
            </dd>
        @endcomponent
        </form>
    @else
        @component('portal.ninja2020.components.general.card-element-single', ['title' => ctrans('texts.direct_debit'), 'show_title' => false])
            <span>{{ ctrans('texts.bank_account_not_linked') }}</span>

            <a class="button button-link text-primary"
                href="{{ route('client.payment_methods.index') }}">{{ ctrans('texts.add_payment_method') }}</a>
        @endcomponent
    @endif

    @if (count($tokens) > 0)
        @include('portal.ninja2020.gateways.includes.pay_now')
    @endif
</div>

@script
    <script>
        Array
            .from(document.getElementsByClassName('toggle-payment-with-token'))
            .forEach((element) => element.addEventListener('click', (element) => {
                document.querySelector('input[name=source]').value = element.target.dataset.token;
            }));

        document.getElementById('pay-now').addEventListener('click', function() {
            document.getElementById('server-response').submit();
        });
        
        const first = document.querySelector('input[name="payment-type"]');

        if (first) {
            first.click();
        }

    </script>
@endscript