@extends('master')

@section('body')

{!! Form::open(array('url' => 'get_started?' . request()->getQueryString(), 'id' => 'startForm')) !!}
{!! Form::hidden('guest_key') !!}
{!! Form::hidden('sign_up', Input::get('sign_up')) !!}
{!! Form::hidden('redirect_to', Input::get('redirect_to')) !!}
{!! Form::close() !!}

<script>
    if (isStorageSupported()) {
        $('[name="guest_key"]').val(localStorage.getItem('guest_key'));
    }

    $(function() {
        $('#startForm').submit();
    })

    function isStorageSupported() {
        if ('localStorage' in window && window['localStorage'] !== null) {
          var storage = window.localStorage;
      } else {
          return false;
      }
      var testKey = 'test';
      try {
          storage.setItem(testKey, '1');
          storage.removeItem(testKey);
          return true;
      } catch (error) {
          return false;
      }
  }
</script>

@stop
