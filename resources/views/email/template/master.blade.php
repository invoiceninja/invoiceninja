@php
if(!isset($design)) $design = 'light';
@endphp

<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
  </head>

<style>
  :root {
    @isset($settings)
    --primary-color: {{ $settings->primary_color }};
    @else
    --primary-color: #4caf50;
    @endisset
  }

  .border-primary {
    border-color: var(--primary-color);
  }
</style>

@if($design == 'dark')
<style>
  * {
    color: #cbd5e0 !important;
  }
</style>
@endif

<body class="{{ $design == 'light' ? 'bg-gray-200' : 'bg-gray-800' }} my-10 font-sans {{ $design == 'light' ? 'text-gray-700' : 'text-gray-400' }}">
  <div class="grid grid-cols-6">
    <div class="col-span-4 col-start-2">
      <div class="{{ $design == 'light' ? 'bg-white' : 'bg-gray-900' }} shadow border-t-2 {{ $design == 'light' ? 'border-primary' : 'border-gray-800' }}">
        <div class="px-10">
          {{ $header }}
        </div>
        <div id="text" class="flex flex-col px-10 py-6">
          @isset($greeting)
          {{ $greeting }}
          @endisset

          <div class="break-all">
            {{ $slot}}
          </div>

          @isset($signature)
          {{ $signature }}
          @endisset
        </div>
      </div>

      @isset($below_card)
      <div id="bottomText" class="px-10 my-4 text-center">
        {{ $below_card }}
      </div>
      @endisset

    </div>
  </div>

  <!-- Whitelabel -->
  @isset($whitelabel)
    @if(!$whitelabel)
      <div style="display: flex; flex-direction: row; justify-content: center; margin-top: 1rem; margin-bottom: 1rem;">
          <a href="https://invoiceninja.com" target="_blank">
            <img style="height: 4rem; {{ $design == 'dark' ? 'filter: invert(100%);' : '' }}" src="{{ asset('images/created-by-invoiceninja-new.png') }}" alt="Invoice Ninja">
          </a>
      </div>
    @endif
  @endif
</body>
</html>