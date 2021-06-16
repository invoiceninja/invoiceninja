@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'ACH (Verification)', 'card_title' => 'ACH (Verification)'])

@section('gateway_content')
    @if(session()->has('error'))
        <div class="alert alert-failure mb-4">{{ session('error') }}</div>
    @endif

    <form method="POST">
        @csrf
        <input type="hidden" name="token" value="{{ $token->token }}">

        @component('portal.ninja2020.components.general.card-element', ['title' => '#1 ' . ctrans('texts.amount_cents')])
            <input type="text" name="transactions[]" class="w-full input" required data-cy="verification-1st">
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => '#2 ' . ctrans('texts.amount_cents')])
            <input type="text" name="transactions[]" class="w-full input" required data-cy="verification-2nd">
        @endcomponent

        @component('portal.ninja2020.gateways.includes.pay_now', ['type' => 'submit'])
            {{ ctrans('texts.complete_verification')}}
        @endcomponent
    </form>
@endsection
