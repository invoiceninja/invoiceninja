@extends('layouts.ninja')
@section('meta_title', ctrans('texts.new_bank_account'))


@push('head')
   <script type='text/javascript' src='https://cdn.yodlee.com/fastlink/v4/initialize.js'></script>
@endpush

@section('body')

<div id="container-fastlink">
    <div style="text-align: center;">
   <input type="submit" id="btn-fastlink" value="Link an Account">
</div>
</div>

@endsection


@push('footer')

<script>
    (function (window) {
      //Open FastLink
      var fastlinkBtn = document.getElementById('btn-fastlink');
      fastlinkBtn.addEventListener(
        'click', 
        function() {
            window.fastlink.open({
              fastLinkURL: '{{ $fasttrack_url }}',
              accessToken: 'Bearer {{ $access_token }}',
              params: {
                configName : 'Example2'
              },
              onSuccess: function (data) {
                // will be called on success. For list of possible message, refer to onSuccess(data) Method.
                console.log(data);
              },
              onError: function (data) {
                // will be called on error. For list of possible message, refer to onError(data) Method.
                console.log(data);
              },
              onClose: function (data) {
                // will be called called to close FastLink. For list of possible message, refer to onClose(data) Method.
                console.log(data);
              },
              onEvent: function (data) {
                // will be called on intermittent status update.
                console.log(data);
              }
            },
            'container-fastlink');
          },
      false);
    }(window));
</script>

@endpush