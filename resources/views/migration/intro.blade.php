@extends('migration.layout.main', [
    'step_title' => 'Start the Migration',
    'step_description' => 'We need you to chose the solution.'
])

@section('body')
    <p class="text-center">Please, chose which solution you'd like to use in the future.</p>
    <div class="flex items-between my-5">
        <a href="/migration/hosted/credentials" class="w-1/2 flex flex-col items-center justify-center border hover:border-blue-700 rounded-lg py-16 px-10 mx-4">
            <p class="block text-2xl font-semibold">Hosted solution</p>
            <p class="block text-center mt-2">Switch to managed version and continue using Invoice Ninja on official domain.</p>
        </a>
        <a href="/migration/self_hosted/credentials" class="w-1/2 flex flex-col items-center justify-center border hover:border-blue-700 rounded-lg py-16 px-10 mx-4">
            <p class="block text-2xl text-center font-semibold">Self-hosted solution</p>
            <p class="block text-center mt-2">Migrate your Invoice Ninja on own server, away from official servers.</p>
        </a>
    </div>
@stop
