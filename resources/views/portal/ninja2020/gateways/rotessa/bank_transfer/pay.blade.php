@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Direct Debit', 'card_title' => 'Direct Debit'])

@section('gateway_content')
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
            <input type="hidden" name="token_id" value="">
            <input type="hidden" name="frequency" value="Once">
            <input type="hidden" name="installments" value="1">
            <input type="hidden" name="comment" value="Payment for invoice # {{ $invoice_nums }}">

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
            @if (count($tokens) > 0)
                @foreach ($tokens as $token)
                    <label class="mr-4">
                        <input type="radio" data-token="{{ $token->token }}" name="payment-type"
                            class="form-radio cursor-pointer toggle-payment-with-token" />
                        <span class="ml-1 cursor-pointer">
                            {{ App\Models\GatewayType::getAlias($token->gateway_type_id) }} ({{ $token->meta->brand }})
                             &nbsp; Acc#: {{ $token->meta->account_number }}
                        </span>
                    </label><br/>
                @endforeach
            @endisset
            <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                Process Date
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                <input autocomplete="new-password" readonly type="date" min="{{ $due_date }}" name="process_date" id="process_date" required class="input w-full" placeholder="" value="{{ old('process_date', $process_date ) }}">
            </dd>
            {{--
            <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                Insallments
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                <input class="input w-full" id="installments" name="installments" type="number" placeholder="Installments" required value="{{ old('installments',$installments) }}">
            </dd>
            
            <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                Frequency
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                <input class="input w-full" id="frequency" name="frequency" type="text" placeholder="Once/Weekly/Monthly/Annually" required >
            </dd>
            
            <dt class="text-sm leading-5 font-medium text-gray-500 mr-4">
                Comments
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                <textarea autocomplete="new-password" id="comment" name="comment" type="text" class="w-full py-2 px-3 rounded text-sm disabled:opacity-75 disabled:cursor-not-allowed undefined border border-gray-300" placeholder="" rows="5" style="background-color: rgb(255, 255, 255); border-color: rgb(209, 213, 219); color: rgb(42, 48, 61);"> </textarea>
            </dd> --}}
        @endcomponent
        </form>
    @else
        @component('portal.ninja2020.components.general.card-element-single', ['title' => 'Direct Debit', 'show_title' => false])
            <span>{{ ctrans('texts.bank_account_not_linked') }}</span>

            <a class="button button-link text-primary"
                href="{{ route('client.payment_methods.index') }}">{{ ctrans('texts.add_payment_method') }}</a>
        @endcomponent
    @endif

    @if (count($tokens) > 0)
        @include('portal.ninja2020.gateways.includes.pay_now')
    @endif
@endsection

@push('footer')
    <script>
        Array
            .from(document.getElementsByClassName('toggle-payment-with-token'))
            .forEach((element) => element.addEventListener('click', (element) => {
                document.querySelector('input[name=source]').value = element.target.dataset.token;
            }));

        document.getElementById('pay-now').addEventListener('click', function() {
            document.getElementById('server-response').submit();
        });
    </script>
@endpush
