@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'ACSS (Verification)', 'card_title' => 'ACSS (Verification)'])

@section('gateway_content')
    @if(session()->has('error'))
        <div class="alert alert-failure mb-4">{{ session('error') }}</div>
    @endif

    <form method="POST">
        @csrf
        <input type="hidden" name="customer" value="{{ $token->gateway_customer_reference }}">
        <input type="hidden" name="source" value="{{ $token->token }}">

        @component('portal.ninja2020.components.general.card-element', ['title' => '#1 ' . ctrans('texts.amount_cents')])
            <input type="text" name="transactions[]" class="w-full input" required dusk="verification-1st" value="{{ old('transactions.0') }}">

            @error('transactions.0')
                <div class="validation validation-fail">
                    {{ $message }}
                </div>
            @enderror
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => '#2 ' . ctrans('texts.amount_cents')])
            <input type="text" name="transactions[]" class="w-full input" required dusk="verification-2nd" value="{{ old('transactions.1') }}">

            @error('transactions.1')
                <div class="validation validation-fail">
                    {{ $message }}
                </div>
            @enderror
        @endcomponent

        @component('portal.ninja2020.gateways.includes.pay_now', ['type' => 'submit'])
            {{ ctrans('texts.complete_verification')}}
        @endcomponent
    </form>
@endsection
