@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.account_management'))

@section('body')

<!-- This example requires Tailwind CSS v2.0+ -->
<div class="bg-white shadow overflow-hidden sm:rounded-lg">
  <div class="px-4 py-5 sm:px-6">
    <h3 class="text-lg leading-6 font-medium text-gray-900">
      {{ ctrans('texts.plan_status') }}
    </h3>
  </div>
  <div class="border-t border-gray-200">
    <dl>
      <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
        <dt class="text-sm font-medium text-gray-500">
      	{{ ctrans('texts.plan') }}
        </dt>
        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
      	{{ $account->plan ? ucfirst($account->plan) : 'Free' }}
        </dd>
      </div>
      @if($account->plan)

      <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
        <dt class="text-sm font-medium text-gray-500">
    	  {{ ctrans('texts.expires') }}
        </dt>
        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
          {{ $client->translateDate($account->plan_expires, $client->date_format(), $client->locale()) }}
        </dd>
      </div>

	      @if($account->plan == 'enterprise')

	      <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
	        <dt class="text-sm font-medium text-gray-500">
	          {{ ctrans('texts.users')}}
	        </dt>
	        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
	        	{{ $account->num_users }}
	        </dd>
	      </div>

	      @endif

      @endif

      @if($late_invoice)

	  <div class="px-4 py-5 sm:px-6">
	    <h3 class="text-lg leading-6 font-medium text-gray-900">
	      {{ ctrans('texts.invoice_status_id') }}
	    </h3>
	    <p class="mt-1 max-w-2xl text-sm text-gray-500">
	      {{ ctrans('texts.past_due') }}
	    </p>
	  </div>
		<dl>
		<div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
		<dt class="text-sm font-medium text-gray-500">
		{{ ctrans('texts.invoice') }}
		</dt>
		<dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
		{{ $late_invoice->number }} - {{ \App\Utils\Number::formatMoney($late_invoice->balance, $client) }} <a class="button-link text-primary" href="/client/invoices/{{$late_invoice->hashed_id}}">{{ ctrans('texts.pay_now')}}</a>
		</dd>
		</div>
		</dl>

      @else

      <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
        <dt class="text-sm font-medium text-gray-500">
    	  {{ ctrans('texts.plan_change') }}
        </dt>
        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
          <div>
          <select id="newPlan" class="pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
          	<option value="">Select Plan</option>
          	@foreach($plans as $plan)
          		<option value="{{ $plan->hashed_id}}">{{ $plan->name }}  {{ \App\Utils\Number::formatMoney($plan->promo_price, $client) }}</option>
          	@endforeach
          </select>

          @if($current_recurring_id)
          <button id="handlePlanChange" class="bg-transparent hover:bg-blue-500 text-blue-700 font-semibold hover:text-white py-2 px-4 border border-blue-500 hover:border-transparent rounded">
                {{ ctrans('texts.plan_change') }}
          </button>
          @else
          <button id="handleNewPlan" class="bg-transparent hover:bg-blue-500 text-blue-700 font-semibold hover:text-white py-2 px-4 border border-blue-500 hover:border-transparent rounded">
                {{ ctrans('texts.plan_upgrade') }}
          </button>          
          @endif
        </dd>
      </div>

      @endif

    </dl>
  </div>
</div>

@endsection

@push('footer')

<script type="text/javascript">

@if($current_recurring_id)
document.getElementById('handlePlanChange').addEventListener('click', function() {
  
  	if(document.getElementById("newPlan").value.length > 1)
  		location.href = 'https://invoiceninja.invoicing.co/client/subscriptions/{{ $current_recurring_id }}/plan_switch/' + document.getElementById("newPlan").value + '';

});
@else
document.getElementById('handleNewPlan').addEventListener('click', function() {
  
	if(document.getElementById("newPlan").value.length > 1)
    	location.href = 'https://invoiceninja.invoicing.co/client/subscriptions/' + document.getElementById("newPlan").value + '/purchase';

});
@endif

</script>
@endpush