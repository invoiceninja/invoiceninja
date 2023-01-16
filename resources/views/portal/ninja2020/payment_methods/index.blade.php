@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.payment_methods'))

@section('body')
    <div class="flex flex-col">
        @livewire('payment-methods-table', ['client_id' => $client->id, 'db' => $company->db])
    </div>
@endsection
