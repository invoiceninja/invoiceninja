@extends('portal.ninja2020.layout.clean')

@section('body')
    <div class="flex h-screen">
        <div class="m-auto md:w-1/3 lg:w-1/5">
            <div class="flex flex-col">
                <h1 class="text-center text-3xl">{{ ctrans('texts.password') }}</h1>
                <p class="text-sm text-center text-gray-700">{{ ctrans('texts.to_view_entity_set_password', ['entity' => $entity_type]) }}</p>
                <form action="{{ route('client.set_password') }}" method="post" >
                    <input type="hidden" name="entity_type" value="{{ $entity_type }}">
                    <input type="hidden" name="invitation_key" value="{{ $invitation_key }}">
                    @csrf
                    <div class="flex flex-col">
                        <div class="flex justify-between items-center">
                        </div>
                        <input type="password" name="password" id="password" class="input" minlength="7" autofocus>
                    </div>
                    <div class="mt-5">
                        <button class="button button-primary button-block bg-blue-600">{{ ctrans('texts.continue') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection