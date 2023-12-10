@extends('layouts.ninja')
@section('meta_title', ctrans('texts.new_bank_account'))

@push('head')
   <script type='text/javascript' src='https://cdn.yodlee.com/fastlink/v4/initialize.js'></script>

   <style>
    .loader {
      border-top-color: #3498db;
      -webkit-animation: spinner 1.5s linear infinite;
      animation: spinner 1.5s linear infinite;
    }

    @-webkit-keyframes spinner {
      0% {
        -webkit-transform: rotate(0deg);
      }
      100% {
        -webkit-transform: rotate(360deg);
      }
    }

    @keyframes spinner {
      0% {
        transform: rotate(0deg);
      }
      100% {
        transform: rotate(360deg);
      }
    }

   </style>

   <script type="text/javascript">


    </script>
@endpush

@section('body')


<div class="flex flex-col justify-center items-center mt-10" id="container-fastlink">
    <div class="mb-4">
        @if($account && !$account->isPaid())
          <div class="max-h-28">
              <img src="{{ asset('images/invoiceninja-black-logo-2.png') }}"
                   class="border-b border-gray-100 h-18 pb-4" alt="Invoice Ninja logo">
          </div>
        @elseif(isset($company) && !is_null($company))
          <div class="max-h-28">
              <img src="{{ $company->present()->logo()  }}"
                   class="mx-auto border-b border-gray-100 h-18 pb-4" style="max-width: 400px;" alt="{{ $company->present()->name() }} logo">
          </div>
        @endif
    </div>

    <div id="cta" class="mb-4" x-data="{ open: false }">

      <button @click="open = !open" x-show="!open" type="submit" class="button button-primary bg-blue-600 my-4" id="btn-fastlink">{{ ctrans('texts.add_bank_account') }}</button>

      <div x-show="open" class="fixed top-0 left-0 right-0 bottom-0 w-full h-screen z-50 overflow-hidden bg-gray-700 opacity-75 flex flex-col items-center justify-center">
        <div class="loader ease-linear rounded-full border-4 border-t-4 border-gray-200 h-12 w-12 mb-4"></div>
        <h2 class="text-center text-gray text-xl font-semibold">Loading...</h2>
        <p class="w-1/3 text-center text-gray">This may take a few seconds, please don't close this page.</p>
      </div>

    </div>

    <div id="completed" class="mb-4">
      <a class="button button-primary bg-blue-600 my-4" href="{{ $redirect_url }}">Return to admin portal.</a>
    </div>

</div>

@endsection

@push('footer')

<script>

  var completed = document.getElementById('completed');
  completed.style.display = "none"; //block

  (function (window) {
    //Open FastLink

    @if($completed)

      var completed = document.getElementById('completed');
      completed.style.display = "block"; //block
      var hideme = document.getElementById('cta');
      hideme.style.display = "none";

    @endif

    var fastlinkBtn = document.getElementById('btn-fastlink');
    fastlinkBtn.addEventListener(
      'click', 
      function() {
          window.fastlink.open({
            flow: '{{ $flow }}',//flow changes depending on what we are doing sometimes it could be add/edit etc etc
            fastLinkURL: '{{ $fasttrack_url }}',
            accessToken: 'Bearer {{ $access_token }}',
            params: {
              configName : '{{ $config_name }}'
            },
            onSuccess: function (data) {
              // will be called on success. For list of possible message, refer to onSuccess(data) Method.
              console.log('success');
              console.log(data);
            },
            onError: function (data) {
              // will be called on error. For list of possible message, refer to onError(data) Method.
              console.log('error');
              
              console.log(data);
            },
            onClose: function (data) {
              // will be called called to close FastLink. For list of possible message, refer to onClose(data) Method.
              console.log('onclose');
              console.log(data);

                  var completed = document.getElementById('completed');
                  completed.style.display = "block"; //block

                  window.location.href = window.location.pathname + "?window_closed=true";

            },
            onEvent: function (data) {
              // will be called on intermittent status update.
              console.log('on event');
              var hideme = document.getElementById('cta');
              hideme.style.display = "none";
              console.log(data);
            }
          },
          'container-fastlink');
        },
    false);
  }(window));

</script>

@endpush