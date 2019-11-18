@extends('migration.layout.main', [
    'step_title' => 'Companies',
    'step_description' => 'Let\'s see what to do with your data.'
])

@section('body')
    <p class="text-center">Looks like you already have one or more companies.</p>
    <p class="block text-center">Please, select one or more companies and solution you'd like to use.</p>

    <form action="/migration/companies" method="post">
        {{ csrf_field() }}

        @if(count($companies))

            <div class="mt-5">
                @foreach($companies as $company)
                    <label class="flex items-center">
                        <input type="checkbox" class="mr-1" value="{{ $company->id }}" name="companies[]">
                        <span>{{ $company->settings->name }} ({{ $company->id }})</span>
                    </label>
                @endforeach
            </div>

            <div class="flex items-between my-5">

                <button name="purge_without_settings" type="submit"
                        class="w-1/2 bg-white shadow flex flex-col items-center justify-center hover:shadow-lg rounded-lg py-16 px-10 mxM-4">
                    <p class="text-center block text-2xl font-semibold">Purge all data and re-import</p>
                    <p class="block text-center mt-2">We'll wipe all information about the company and merge the one
                        from
                        V1.</p>
                </button>
                <button name="purge_with_settings" type="submit"
                        class="w-1/2 bg-white shadow flex flex-col items-center justify-center hover:shadow-lg rounded-lg py-16 px-10 mx-4">
                    <p class="text-center block text-2xl text-center font-semibold">Purge all data, but save
                        settings</p>
                    <p class="block text-center mt-2">We will migrate all information about your company but keep the
                        latest
                        settings from V2.</p>
                </button>
            </div>
        @endif
    </form>

@stop
