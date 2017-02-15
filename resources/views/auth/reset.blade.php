@extends('login')

@section('form')
<div class="container">

  {!! Former::open('/password/reset')->addClass('form-signin')->rules(array(
        'password' => 'required',
        'password_confirmation' => 'required',
  )) !!}

    <h2 class="form-signin-heading">{{ trans('texts.set_password') }}</h2>
    <hr class="green">

    @if (count($errors->all()))
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
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

    <input type="hidden" name="token" value="{{{ $token }}}">

    <div>
        {!! Former::text('email')->placeholder(trans('texts.email'))->raw() !!}
        {!! Former::password('password')->placeholder(trans('texts.password'))->raw() !!}
        {!! Former::password('password_confirmation')->placeholder(trans('texts.confirm_password'))->raw() !!}
    </div>

    <p>{!! Button::success(trans('texts.save'))->large()->submit()->withAttributes(['class' => 'green'])->block() !!}</p>


    {!! Former::close() !!}
</div>
<script type="text/javascript">
    $(function() {
        $('#email').focus();
    })
</script>

@endsection
