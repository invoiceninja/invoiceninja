@section('meta_title', __('texts.payment_methods'))
@extends('portal.default.layouts.master')

@section('body')
    <main class="main">
        <div class="container-fluid">
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            {{ ctrans("texts.{$payment_method->gateway_type->alias}") }}
                        </div>
                        <div class="card-body">
                            <p>
                                <b>{{ ctrans('texts.payment_type') }}:</b>
                                {{ $payment_method->gateway_type->name }}
                            </p>
                            <p>
                                <b>{{ ctrans('texts.type') }}:</b>
                                {{ ucfirst($payment_method->meta->brand) }}
                            </p>

                            <p>
                                <b>{{ ctrans('texts.card_number') }}:</b>
                                **** **** **** {{ ucfirst($payment_method->meta->last4) }}
                            </p>

                            @isset($payment_method->meta->exp_month)
                            <p>
                                <b>{{ ctrans('texts.expires') }}:</b>
                                {{ "{$payment_method->meta->exp_month}/{$payment_method->meta->exp_year}" }}
                            </p>
                            @endisset

                            <p>
                                <b>{{ ctrans('texts.date_created') }}:</b>
                                {{ date(auth()->user()->client->date_format(), $payment_method->created_at) }}
                            </p>
                            <p class="mb-0">
                                <b>{{ ctrans('texts.default') }}:</b>
                                {{ $payment_method->is_default ? ctrans('texts.yes') : ctrans('texts.no') }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            {{ ctrans("texts.delete_payment_method") }}
                        </div>
                        <div class="card-body">
                            <p class="mb-0">
                                {{ ctrans('texts.about_to_delete_payment_method') }}
                                {{ ctrans('texts.action_cant_be_reversed') }}.
                            </p>
                        </div>
                        <div class="card-footer d-flex justify-content-end">
                            {!! Former::horizontal_open()->route('client.payment_methods.destroy', $payment_method->hashed_id)->method('DELETE') !!}
                                <button class="btn btn-danger btn-sm">{{ ctrans('texts.i_understand_delete') }}</button>
                            {!! Former::close() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
