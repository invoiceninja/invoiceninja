@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.projects'))

@section('body')
    <div class="flex flex-col mt-4">
        @livewire('projects-table', [
            'company_id' => auth()->guard('contact')->user()->company->id,
            'db' => auth()->guard('contact')->user()->company->db,
        ])
    </div>
@endsection
