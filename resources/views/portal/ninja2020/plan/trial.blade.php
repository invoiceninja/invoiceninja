@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.account_management'))

@section('body')

<meta name="stripe-publishable-key" content="{{ $gateway->getPublishableKey()}}">
<meta name="client-postal-code" content="{{ $client->postal_code }}">
<meta name="client-name" content="{{ $client->present()->name() }}">

<div class="flex flex-wrap overflow-hidden">

  <div class="w-1/2 overflow-hidden">
    <h1 style="font-size:24px;">Start your 14 day Pro Trial!</h1>
    <p class="mt-6">Enjoy 14 days of our Pro Plan</p>

<div>

    <ul class="mx-20 w-100" style="list-style-type:disc;">
      <li>Unlimited Clients & Invoices & Quotes</li>
      <li>Remove "Created by Invoice Ninja"</li>
      <li>10 Professional Invoice & Quote Templates</li>
      <li>Send Invoice Emails Sent via Your Gmail</li>
      <li>Attach Invoice PDF's to Client Emails</li>
      <li>Customize Auto-Reminder Emails</li>
      <li>Display Client E-Signatures on Invoices</li>
      <li>Enable a Client "Approve Terms' Checkbox</li>
      <li>Interlink Multiple Companies (x10) with 1 Login</li>
      <li>Customize Invoice Designs & Email Templates</li>
      <li>Custom Settings for Different Client "Groups"</li>
      <li>Client Subscriptions: Recurring & Auto-billing</li>
      <li>Password Protected Client-Side Portal</li>
      <li>API Integration with 3rd Party Apps</li>
      <li>& Much More!</li>
    </ul>

</div>

</div>
  <div class="w-1/2 overflow-hidden">

  <form id="card-form" action="{{ route('client.trial.response') }}" method="post">
    @csrf
    <input type="hidden" name="gateway_response">
    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    <div class="form-group mb-2">
      <input type="text" class="form-control block
        w-full
        px-3
        py-2
        text-base
        font-normal
        text-gray-700
        bg-white bg-clip-padding
        border border-solid border-gray-300
        rounded
        transition
        ease-in-out
        m-0
        focus:text-gray-700 focus:bg-white focus:border-blue-600 focus:outline-none" id="name"
        placeholder="{{ ctrans('texts.name') }}"
        name="name"
        value="{{$client->present()->name()}}">
    </div>

    <div class="form-group mb-2">
      <input type="text" class="form-control block
        w-full
        px-3
        py-2
        text-base
        font-normal
        text-gray-700
        bg-white bg-clip-padding
        border border-solid border-gray-300
        rounded
        transition
        ease-in-out
        m-0
        focus:text-gray-700 focus:bg-white focus:border-blue-600 focus:outline-none" id="address1"
        placeholder="{{ ctrans('texts.address1') }}"
        name="address1"
        value="{{$client->address1}}">
    </div>
    <div class="form-group mb-2">
      <input type="text" class="form-control block
        w-full
        px-3
        py-2
        text-base
        font-normal
        text-gray-700
        bg-white bg-clip-padding
        border border-solid border-gray-300
        rounded
        transition
        ease-in-out
        m-0
        focus:text-gray-700 focus:bg-white focus:border-blue-600 focus:outline-none" id="address2"
        placeholder="{{ ctrans('texts.address2') }}"
        name="address2"
        value="{{$client->address2}}">
    </div>

  <div class="flex form-group mb-2">

    <div class="w-full gap-x-2 md:w-1/3">
      <div class="form-group">
        <input type="text" class="form-control block
          w-full
          px-3
          py-2
          text-base
          font-normal
          text-gray-700
          bg-white bg-clip-padding
          border border-solid border-gray-300
          rounded
          transition
          ease-in-out
          m-0
          focus:text-gray-700 focus:bg-white focus:border-blue-600 focus:outline-none" id="city"
          placeholder="{{ ctrans('texts.city') }}"
          name="city"
          value="{{$client->city}}">
      </div>
    </div>

    <div class="w-full gap-x-2 md:w-1/3">
      <div class="form-group">
        <input type="text" class="form-control block
          w-full
          px-3
          py-2
          text-base
          font-normal
          text-gray-700
          bg-white bg-clip-padding
          border border-solid border-gray-300
          rounded
          transition
          ease-in-out
          m-0
          focus:text-gray-700 focus:bg-white focus:border-blue-600 focus:outline-none" id="state"
          placeholder="{{ ctrans('texts.state') }}"
          name="state"
          value="{{$client->state}}">
      </div>
    </div>

    <div class="w-full gap-x-2 md:w-1/3">
      <div class="form-group">
        <input type="text" class="form-control block
          w-full
          px-3
          py-2
          text-base
          font-normal
          text-gray-700
          bg-white bg-clip-padding
          border border-solid border-gray-300
          rounded
          transition
          ease-in-out
          m-0
          focus:text-gray-700 focus:bg-white focus:border-blue-600 focus:outline-none" id="postal_code"
          placeholder="{{ ctrans('texts.postal_code') }}"
          name="postal_code"
          value="{{$client->postal_code}}">
      </div>
    </div>
  </div>

  <div class="form-group mb-2">
      <select name="country" id="country" class="form-select w-full py-2 text-gray-700">
          <option value="{{ $client->country->id}}" selected>{{ $client->country->iso_3166_2 }} ({{ $client->country->name }})</option>
          @foreach($countries as $country)
              <option value="{{ $country->id }}">{{ $country->iso_3166_2 }} ({{ $country->name }})></option>
          @endforeach
      </select>
  </div>

  <div class="mb-2">
    <div id="card-element" class="border p-4 rounded
    text-base
    font-normal
    text-gray-700
    bg-white bg-clip-padding
    border border-solid border-gray-300
    focus:text-gray-700 focus:bg-white focus:border-blue-600 focus:outline-none"></div>
  </div>

  <div class="flex justify-end">
      <button
          @isset($form) form="{{ $form }}" @endisset
          type="{{ $type ?? 'button' }}"
          id="{{ $id ?? 'pay-now' }}"
          @isset($data) @foreach($data as $prop => $value) data-{{ $prop }}="{{ $value }}" @endforeach @endisset
          class="button button-primary bg-primary {{ $class ?? '' }}"
          {{ isset($disabled) && $disabled === true ? 'disabled' : '' }}>
              <svg class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
          <span>{{ $slot ?? ctrans('texts.trial_call_to_action') }}</span>
      </button>
  </div>
  <div class="flex justify-end mt-5">
    <span class="text-gray-700" style="font-size:12px;">* At the end of your 14 day trial your card will be charged $10/month. Cancel anytime.</span>
  </div>

  </form>

</div>


<div class="w-full mt-6 pt-6">
  <div class="relative">
    <div class="absolute inset-0 flex items-center" aria-hidden="true">
      <div class="w-full border-t border-gray-300"></div>
    </div>
    <div class="relative flex justify-center">
      <span class="px-2 bg-white text-sm text-gray-500"> Discounted Plans </span>
    </div>
  </div>
</div>


<div class="w-full mt-6 pt-6">
<div class="bg-gray-50">
  <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:py-16 lg:px-8 lg:flex lg:items-center lg:justify-between">
    <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
      <span class="block">Enterprise Plan (1-2 Users) Annual</span>
      <span class="block text-indigo-600 mt-2">Buy 10 months, get 2 free! $140</span>
    </h2>
    <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
      <div class="inline-flex rounded-md shadow">
        <a href="https://invoiceninja.invoicing.co/client/subscriptions/LYqaQWldnj/purchase" class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"> Buy Now! </a>
      </div>
    </div>
  </div>
</div>
</div>

<div class="w-full mt-6 pt-6">
<div class="bg-gray-50">
  <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:py-16 lg:px-8 lg:flex lg:items-center lg:justify-between">
    <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
      <span class="block">Pro Plan Annual</span>
      <span class="block text-indigo-600 mt-2">Buy 10 months, get 2 free! $100</span>
    </h2>
    <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
      <div class="inline-flex rounded-md shadow">
        <a href="https://invoiceninja.invoicing.co/client/subscriptions/q9wdL9wejP/purchase" class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"> Buy Now! </a>
      </div>
    </div>
  </div>
</div>
</div>


@endsection

@push('footer')
<script src="https://js.stripe.com/v3/"></script>

<script type="text/javascript">

var stripe = Stripe('{{ $gateway->getPublishableKey()}}');
var client_secret = '{{ $intent->client_secret }}';

var elements = stripe.elements({
  clientSecret: client_secret,
});

var cardElement = elements.create('card', {
    value: {
        postalCode: document.querySelector('input[name=postal_code]').content,
        name: document.querySelector('input[name=name]').content
    }
});

cardElement.mount('#card-element');

const form = document.getElementById('card-form');

var e = document.getElementById("country");
var country_value = e.options[e.selectedIndex].value;

  document
      .getElementById('pay-now')
      .addEventListener('click', () => {

        let payNowButton = document.getElementById('pay-now');
        payNowButton = payNowButton;
        payNowButton.disabled = true;
        payNowButton.querySelector('svg').classList.remove('hidden');
        payNowButton.querySelector('span').classList.add('hidden');

        stripe.handleCardSetup(this.client_secret, cardElement, {
                payment_method_data: {
                      billing_details: {
                        name: document.querySelector('input[name=name]').content,
                },
              }
            })
            .then((result) => {
                if (result.error) {

                  let errors = document.getElementById('errors');
                  let payNowButton = document.getElementById('pay-now');

                  errors.textContent = '';
                  errors.textContent = result.error.message;
                  errors.hidden = false;

                  payNowButton.disabled = false;
                  payNowButton.querySelector('svg').classList.add('hidden');
                  payNowButton.querySelector('span').classList.remove('hidden');
                  return;

                }

              document.querySelector(
                  'input[name="gateway_response"]'
              ).value = JSON.stringify(result.setupIntent);

                document.getElementById('card-form').submit();
                
              });

      });

</script>
@endpush