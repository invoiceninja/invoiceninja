@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.account_management'))

@section('body')

<!-- This example requires Tailwind CSS v2.0+ -->
<div class="bg-gray-50">
  <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:py-16 lg:px-8 lg:flex lg:items-center lg:justify-between">
    <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
      <span class="block">Invoice Ninja - Pro Plan.</span>
      <span class="block text-indigo-600 mt-2">Your Pro Plan trial is loaded and ready to go!</span>
    </h2>
    <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
      <div class="inline-flex rounded-md shadow">
        <a href="https://invoicing.co" class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"> Account Login </a>
      </div>
    </div>
  </div>
</div>

@endsection