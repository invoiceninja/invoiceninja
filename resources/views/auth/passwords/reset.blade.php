@extends('login')

@section('form')
<div class="container">

  {!! Former::open($url)
        ->addClass('form-signin')
        ->autocomplete('off')
        ->rules(array(
        'email' => 'required|email',
        'password' => 'required',
        'password_confirmation' => 'required',
  )) !!}

    @include('partials.autocomplete_fix')

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

    <div onkeyup="validateForm()" onclick="validateForm()" onkeydown="validateForm(event)">
        {!! Former::text('email')->placeholder(trans('texts.email'))->raw() !!}
        {!! Former::password('password')->placeholder(trans('texts.password'))->autocomplete('new-password')->raw() !!}
        {!! Former::password('password_confirmation')->placeholder(trans('texts.confirm_password'))->autocomplete('new-password')->raw() !!}
    </div>

    <div id="passwordStrength" style="font-weight:normal;padding:16px">
        &nbsp;
    </div>

    <p>{!! Button::success(trans('texts.save'))->large()->submit()->withAttributes(['class' => 'green', 'id' => 'saveButton', 'disabled' => true])->block() !!}</p>


    {!! Former::close() !!}
</div>
<script type="text/javascript">
    $(function() {
        $('#password').focus();
        validateForm();
    })

    function validateForm() {
        var isValid = true;

        if (! $('#email').val()) {
            isValid = false;
        }

        var password = $('#password').val();
        var confirm = $('#password_confirmation').val();

        if (! password || password != confirm || password.length < 8) {
            isValid = false;
        }

        var score = scorePassword(password);
        if (score < 50) {
            isValid = false;
        }

        showPasswordStrength(password, score);

        $('#saveButton').prop('disabled', ! isValid);
    }
</script>

@endsection
