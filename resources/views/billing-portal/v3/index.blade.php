@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.purchase'))

@section('body')
    @livewire('billing-portal.purchase', ['subscription' => $subscription, 'db' => $subscription->company->db, 'hash' => $hash, 'request_data' => $request_data, 'campaign' => request()->query('campaign') ?? null])
@stop
