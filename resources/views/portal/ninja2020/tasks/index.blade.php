@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.tasks'))

@section('body')
    <div class="flex flex-col">
        @livewire('tasks-table', ['company' => $company])
    </div>
@endsection
