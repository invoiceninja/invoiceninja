@extends('login')

@section('form')

    @include('partials.warn_session', ['redirectTo' => '/client/sessionexpired'])

    <div class="container">

        {!! Former::open('client/login')
            ->rules(['password' => 'required'])
            ->addClass('form-signin') !!}

        <h2 class="form-signin-heading">{{ trans('texts.client_login') }}</h2>
        <hr class="green">


        @if (count($errors->all()))
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </div>
        @endif

        @if (Session::has('warning'))
            <div class="alert alert-warning">{{ Session::get('warning') }}</div>
        @endif

        @if (Session::has('message'))
            <div class="alert alert-info">{{ Session::get('message') }}</div>
        @endif

        @if (Session::has('error'))
            <div class="alert alert-danger"><li>{{ Session::get('error') }}</li></div>
        @endif

        {{ Former::populateField('remember', 'true') }}

        <div>
            {!! Former::password('password')->placeholder(trans('texts.password'))->raw() !!}
        </div>
        {!! Former::hidden('remember')->raw() !!}

        {!! Button::success(trans('texts.login'))
                    ->withAttributes(['id' => 'loginButton', 'class' => 'green'])
                    ->large()->submit()->block() !!}

        <div class="row meta">
            <div class="col-md-12 col-sm-12" style="text-align:center;padding-top:8px;">
                {!! link_to('/client/recover_password', trans('texts.recover_password')) !!}
            </div>
        </div>
        {!! Former::close() !!}
    </div>


    <script type="text/javascript">
        $(function() {
            if ($('#email').val()) {
                $('#password').focus();
            } else {
                $('#email').focus();
            }
        })
    </script>

@endsection
