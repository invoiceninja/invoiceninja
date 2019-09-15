@extends('portal.default.layouts.master')
@section('header')

@stop
@section('body')
<main class="main">
    <div class="container-fluid">
		<div class="row" style="padding-top: 30px;">
            <div class="col d-flex justify-content-center">
                <div class="card w-50 p-10">
                    <div class="card-header">
                        {{ ctrans('texts.payment')}}
                    </div>
                    <div class="card-body">
                       <h2>{{ ctrans('texts.add_credit_card')}}</h2>

                       @yield('credit_card')

                    </div>
                </div>
            </div>
		</div>
    </div>
</main>
</body>
@endsection
@push('scripts')
@endpush