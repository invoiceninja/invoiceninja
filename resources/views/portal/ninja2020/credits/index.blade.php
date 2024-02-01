@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.credits'))

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
    <div class="flex flex-col">
        @livewire('credits-table', ['company_id' => $company->id, 'db' => $company->db])
    </div>
@endsection
