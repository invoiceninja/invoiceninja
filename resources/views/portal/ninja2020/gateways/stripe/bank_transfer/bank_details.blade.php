@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Bank Transfer', 'card_title' => $description ])

@section('gateway_content')
        <div class="container mx-auto">
            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">

            @if($currency == 'gbp')

              <dt class="text-sm font-medium leading-5 text-gray-500">
                  {{ ctrans('texts.sort') }}
              </dt>
              <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                  {{ $sort_code }}
              </dd>
              
              <dt class="text-sm font-medium leading-5 text-gray-500">
                  {{ ctrans('texts.account_number') }}
              </dt>
              <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                  {{ $account_number }}
              </dd>

              <dt class="text-sm font-medium leading-5 text-gray-500">
                  {{ ctrans('texts.account_name') }}
              </dt>
              <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                  {{ $account_holder_name }}
              </dd>

              
              <dt class="text-sm font-medium leading-5 text-gray-500">
                  {{ ctrans('texts.reference') }}
              </dt>
              <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                  {{ $reference }}
              </dd>


              <dt class="text-sm font-medium leading-5 text-gray-500">
                  {{ ctrans('texts.balance_due') }}
              </dt>
              <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                  {{ $amount }}
              </dd>

              <dt class="text-sm font-medium leading-5 text-gray-500">
              </dt>
              <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                  {{ ctrans('texts.stripe_direct_debit_details') }}
              </dd>
              
              @elseif($currency == 'mxn')
              
                <dt class="text-sm font-medium leading-5 text-gray-500">
                    Clabe
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $sort_code }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.account_number') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $account_number }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.account_name') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $account_holder_name }}
                </dd>


                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.reference') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $reference }}
                </dd>


                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.balance_due') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $amount }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ ctrans('texts.stripe_direct_debit_details') }}
                </dd>
                
                @elseif($currency == 'jpy')
              
                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.account_number') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $account_number }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.account_name') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $account_holder_name }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.account_type') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $account_type }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.bank_name') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $bank_name }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.bank_code') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $bank_code }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.branch_name') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $branch_name }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.branch_code') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $branch_code }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.reference') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $reference }}
                </dd>


                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.balance_due') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $amount }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ ctrans('texts.stripe_direct_debit_details') }}
                </dd>

            @elseif($currency == 'eur')

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.account_name') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $account_holder_name }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.account_number') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $account_number }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.bic') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $sort_code }}
                </dd>



            @endif
              
            </div>
        </div>
  @endsection

@push('footer')
@endpush