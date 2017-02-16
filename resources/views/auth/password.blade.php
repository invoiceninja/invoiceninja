@extends('login')

@section('form')
<div class="container">

{!! Former::open('recover_password')->rules(['email' => 'required|email'])->addClass('form-signin') !!}

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

    <div>
        {!! Former::text('email')->placeholder(trans('texts.email_address'))->raw() !!}
    </div>
    {!! Button::success(trans('texts.send_email'))->large()->submit()->withAttributes(['class' => 'green'])->block() !!}

    {!! Former::close() !!}

</div>

<script type="text/javascript">
    $(function() {
        $('#email').focus();
    })
</script>

@stop
