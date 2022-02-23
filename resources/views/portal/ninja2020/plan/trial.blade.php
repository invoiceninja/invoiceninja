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

    <ul class="list-decimal mx-20 w-100">
      <li>Unlimited clients, invoices and quotes</li>
      <li>Remove "Created by Invoice Ninja" from invoices</li>
      <li>Enable emails to be sent from your GMail</li>
      <li>Create subscriptions: Recurring & Auto-billing</li>
      <li>API integration with 3rd party apps & platforms</li>
      <li>Custom reminders</li>
      <li>Attach PDF's to client emails</li>
      <li>Display clients e-signature on invoices and quotes</li>
      <li>Enable clients to "Approve Terms' checkbox</li>
      <li>Bulk emailing of invoices and quotes</li>
    </ul>

</div>

  </div>
  <div class="w-1/2 overflow-hidden">

  <form>
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
        focus:text-gray-700 focus:bg-white focus:border-blue-600 focus:outline-none" id="exampleInput91"
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
      <select name="countries" id="country" class="form-select w-full py-2 text-gray-700">
          @foreach($countries as $country)
              <option value="{{ $client->country->iso_3166_2 }}">{{ $client->country->iso_3166_2 }} ({{ $client->country->name }})</option>
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



@endsection

@push('footer')
<script src="https://js.stripe.com/v3/"></script>

<script type="text/javascript">

var stripe = Stripe('{{ $gateway->getPublishableKey()}}');

var elements = stripe.elements({
  clientSecret: '{{ $intent->client_secret }}',
});

var cardElement = elements.create('card', {
    value: {
        postalCode: document.querySelector('meta[name=client-postal-code]').content,
        name: document.querySelector('meta[name=client-name').content
    }
});

cardElement.mount('#card-element');



</script>
@endpush