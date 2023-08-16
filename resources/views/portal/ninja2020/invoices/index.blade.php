@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.invoices'))

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
    <div class="flex items-center">
        <form action="{{ route('client.invoices.bulk') }}" method="post" id="bulkActions">
            @csrf
            <button type="submit" onclick="setTimeout(() => this.disabled = true, 0); setTimeout(() => this.disabled = false, 5000); return true;" class="button button-primary bg-primary" name="action" value="download">{{ ctrans('texts.download') }}</button>
            @csrf
            @if(!empty(auth()->user()->client->service()->getPaymentMethods(0)))
                <button onclick="setTimeout(() => this.disabled = true, 0); return true;" type="submit" class="button button-primary bg-primary" name="action" value="payment">{{ ctrans('texts.pay_now') }}</button>
            @endif
        </form>
    </div>
    <div class="flex flex-col mt-4">
        @livewire('invoices-table', ['company_id' => $company->id, 'db' => $company->db])
    </div>
@endsection
