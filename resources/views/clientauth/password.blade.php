@extends('login')

@section('form')
    @include('partials.warn_session', ['redirectTo' => '/client/sessionexpired'])
    <div class="container">
        {!! Former::open('client/recover_password')->addClass('form-signin') !!}

        <h2 class="form-signin-heading">{{ trans('texts.password_recovery') }}</h2>
        <hr class="green">

        @if (count($errors->all()))
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </div>
        @endif

        @if (session('status'))
            <div class="alert alert-info">
                {{ session('status') }}
            </div>
        @endif

        <!-- if there are login errors, show them here -->
        @if (Session::has('warning'))
            <div class="alert alert-warning">{{ Session::get('warning') }}</div>
        @endif

        @if (Session::has('message'))
            <div class="alert alert-info">{{ Session::get('message') }}</div>
        @endif

        @if (Session::has('error'))
            <div class="alert alert-danger">{{ Session::get('error') }}</div>
        @endif

        {!! Button::success(trans('texts.send_email'))
                    ->withAttributes(['class' => 'green'])
                    ->large()->submit()->block() !!}

        {!! Former::close() !!}
    </div>
@endsection