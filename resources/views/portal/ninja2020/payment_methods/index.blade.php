@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.payment_methods'))

@section('body')

    @section('header')
        @if($errors->any())
            <div class="alert alert-failure mb-4">
                @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
    @endsection

    <div class="flex flex-col">
        @livewire('payment-methods-table', ['client_id' => $client->id, 'db' => $company->db])
    </div>
@endsection
