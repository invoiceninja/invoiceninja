@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.dashboard'))

@section('body')
    <span class="text-center text-xl text-center">
        {{ ctrans('texts.payment_error_code', ['code' => isset($code) ? $code : '']) }}
    </span>

    <span class="mt-6 block">{{ ctrans('texts.common_codes') }}:</span>
    <ul>
        <li>{{ ctrans('texts.payment_error_code_20087') }}.</li>
    </ul>
@endsection
