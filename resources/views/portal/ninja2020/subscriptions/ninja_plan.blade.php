@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.pro_plan_call_to_action'))

@section('body')

<style>
/* Toggle A */
input:checked ~ .dot {
  transform: translateX(100%);
  background-color: #48bb78;
}
/* Toggle B */
input:checked ~ .dot {
  transform: translateX(100%);
  background-color: #48bb78;
}
</style>
<div class="container flex flex-wrap pt-4 pb-10 m-auto mt-6 md:mt-15 lg:px-12 xl:px-16" x-data="{show: true}">
    <div class="w-full px-0 lg:px-4">
      <h2 class="px-12 text-base font-bold text-center md:text-2xl text-blue-700">
        Choose your plan
      </h2>
      <p class="py-1 text-sm text-center text-blue-700 mb-10">
  

            <!-- Toggle B -->
            <div class="flex items-center justify-center w-full mb-12"">
              
              <label for="toggleB" class="flex items-center cursor-pointer">
                <!-- toggle -->
                <div class="relative">
                  <!-- input -->
                  <input type="checkbox" id="toggleB" class="sr-only" @click="show = !show">
                  <!-- line -->
                  <div class="block bg-gray-600 w-14 h-8 rounded-full"></div>
                  <!-- dot -->
                  <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition"></div>
                </div>
                <!-- label -->
                <div class="ml-3 text-gray-700 font-medium">
                 Monthly vs Annual
                </div>
              </label>

            </div>

        </p>

      <!-- monthly Plans -->

      <div class="flex flex-wrap items-center justify-center py-4 pt-0" x-show=" show ">
        <div class="w-full p-4 md:w-1/2 lg:w-1/2">
          <label class="flex flex-col rounded-lg shadow-lg relative cursor-pointer hover:shadow-2xl">
            <div class="w-full px-4 py-8 rounded-t-lg bg-blue-500">
              <h3 class="mx-auto text-base font-semibold text-center underline text-white group-hover:text-white">
                Pro Plan
              </h3>
              <p class="text-5xl font-bold text-center text-white">
                $10
              </p>
              <p class="text-xs text-center uppercase text-white">
                monthly
              </p>
            </div>
            <div class="flex flex-col items-center justify-center w-full h-full py-6 rounded-b-lg bg-blue-700">
              <p class="text-xl text-white">
                Sign up!
              </p>
              <a type="button" class="w-5/6 py-2 mt-2 font-semibold text-center uppercase bg-white border border-transparent rounded text-blue-500" href="https://invoiceninja.invoicing.co/client/subscriptions/WJxbojagwO/purchase">
                Purchase
              </a>
            </div>
          </label>
        </div>


        <div class="w-full p-4 md:w-1/2 lg:w-1/2">
          <label class="flex flex-col rounded-lg shadow-lg relative cursor-pointer hover:shadow-2xl">
            <div class="w-full px-4 py-8 rounded-t-lg bg-blue-500">
              <h3 class="mx-auto text-base font-semibold text-center underline text-white group-hover:text-white">
                Enterprise (1-2 Users)
              </h3>
              <p class="text-5xl font-bold text-center text-white">
                $14
              </p>
              <p class="text-xs text-center uppercase text-white">
                monthly
              </p>
            </div>
            <div class="flex flex-col items-center justify-center w-full h-full py-6 rounded-b-lg bg-blue-700">
              <p class="text-xl text-white">
                Sign up!
              </p>
              <a type="button" class="w-5/6 py-2 mt-2 font-semibold text-center uppercase bg-white border border-transparent rounded text-blue-500" href="https://invoiceninja.invoicing.co/client/subscriptions/7LDdwRb1YK/purchase">
                Purchase
              </a>
            </div>
          </label>
        </div>



      </div>




      <!-- Annual Plans -->
      <div class="flex flex-wrap items-center justify-center py-4 pt-0" x-show=" !show ">
        <div class="w-full p-4 md:w-1/2 lg:w-1/2">
          <label class="flex flex-col rounded-lg shadow-lg relative cursor-pointer hover:shadow-2xl">
            <div class="w-full px-4 py-8 rounded-t-lg bg-blue-500">
              <h3 class="mx-auto text-base font-semibold text-center underline text-white group-hover:text-white">
                Pro Plan
              </h3>
              <p class="text-5xl font-bold text-center text-white">
                $100
              </p>
              <p class="text-xs text-center uppercase text-white">
                yearly
              </p>
            </div>
            <div
              class="flex flex-col items-center justify-center w-full h-full py-6 rounded-b-lg bg-blue-700"
            >
              <p class="text-xl text-white">
                Buy 10 get 2 free!
              </p>
                <a type="button" class="w-5/6 py-2 mt-2 font-semibold text-center uppercase bg-white border border-transparent rounded text-blue-500" href="https://invoiceninja.invoicing.co/client/subscriptions/q9wdL9wejP/purchase">
                Purchase
              </a>
            </div>
          </label>
        </div>

        <div class="w-full p-4 md:w-1/2 lg:w-1/2">
          <label class="flex flex-col rounded-lg shadow-lg relative cursor-pointer hover:shadow-2xl">
            <div class="w-full px-4 py-8 rounded-t-lg bg-blue-500">
              <h3 class="mx-auto text-base font-semibold text-center underline text-white group-hover:text-white">
                Enterprise (1-2 Users)
              </h3>
              <p class="text-5xl font-bold text-center text-white">
                $140
              </p>
              <p class="text-xs text-center uppercase text-white">
                yearly
              </p>
            </div>
            <div
              class="flex flex-col items-center justify-center w-full h-full py-6 rounded-b-lg bg-blue-700"
            >
              <p class="text-xl text-white">
                Buy 10 get 2 free!
              </p>
                <a type="button" class="w-5/6 py-2 mt-2 font-semibold text-center uppercase bg-white border border-transparent rounded text-blue-500" href="https://invoiceninja.invoicing.co/client/subscriptions/LYqaQWldnj/purchase">
                Purchase
              </a>
            </div>
          </label>
        </div>

      </div>

    </div>
  </div>
@endsection

@push('footer')


@endpush
