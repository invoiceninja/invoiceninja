@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.payments'))

@section('body')
    <div class="flex flex-col">
        @livewire('payments-table', ['company_id' => $company->id, 'db' => $company->db])
    </div>
@endsection