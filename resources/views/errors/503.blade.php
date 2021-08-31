@extends('portal.ninja2020.layout.error')

@section('title', __('Service Unavailable'))
@section('code', '503')
@section('message', __($exception->getMessage() ?: 'Service Unavailable'))
