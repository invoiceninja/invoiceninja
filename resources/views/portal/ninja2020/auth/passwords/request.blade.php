@extends('portal.ninja2020.layout.clean')
@section('meta_title', $title)

@section('body')
    <div class="grid lg:grid-cols-3">
        @if(\App\Models\Account::count() > 0 && !\App\Models\Account::first()->isPaid())
        <div class="hidden lg:block col-span-1 bg-red-100 h-screen">
            <img src="https://www.invoiceninja.com/wp-content/uploads/2018/04/bg-home2018b.jpg"
                 class="w-full h-screen object-cover"
                 alt="Background image">
        </div>
        @endif
        <div class="col-span-2 h-screen flex">
            <div class="m-auto w-1/2 md:w-1/3 lg:w-1/4">
                <div class="flex flex-col">
                    <h1 class="text-center text-3xl">{{ ctrans('texts.password_recovery') }}</h1>
                    <p class="text-center mt-1 text-gray-600">{{ ctrans('texts.reset_password_text') }}</p>
                    @if(session('status'))
                        <div class="alert alert-success mt-4">
                            {{ session('status') }}
                        </div>
                    @endif
                    <form action="{{ route($passwordEmailRoute) }}" method="post" class="mt-6">
                        @csrf
                        <div class="flex flex-col">
                            <label for="email" class="input-label">{{ ctrans('texts.email_address') }}</label>
                            <input type="email" name="email" id="email"
                                   class="input"
                                   value="{{ request()->query('email') ?? old('email') }}"
                                   autofocus>
                            @error('email')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="mt-5">
                            <button class="button button-primary button-block bg-blue-600">{{ ctrans('texts.next_step') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
