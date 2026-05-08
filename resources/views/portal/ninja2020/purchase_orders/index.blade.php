@extends('portal.ninja2020.layout.vendor_app')
@section('meta_title', ctrans('texts.purchase_orders'))

@section('header')
    @if($errors->any())
        <div class="alert alert-failure mb-4">
            @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif
@endsection

@section('body')
    <div class="flex flex-col mt-4">
        @livewire('purchase-orders-table', ['company_id' => $company->id, 'db' => $company->db])
    </div>
@endsection
