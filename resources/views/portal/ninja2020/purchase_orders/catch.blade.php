@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.vendor'))

@component('portal.ninja2020.components.test')
@endcomponent

@section('body')
<div class="flex justify-center items-center h-screen">
  <h1>Vendor Portal</h1>
</div>
@endsection
