<div>
    @if (session()->has('message'))
        @component('portal.ninja2020.components.message')
            {{ session('message') }}
        @endcomponent
    @endif

    <div class="my-4">
        <h1 class="text-3xl font-medium">{{ ctrans('texts.contact') }}</h1>
    </div>

    @if($state['initial_completed'] === false)
        <form wire:submit="initial">
            @csrf

            <label for="email_address">
                <span class="input-label">{{ ctrans('texts.email_address') }}</span>
                <input wire:model="email" type="email" class="input w-full" />

                @error('email')
                <p class="validation validation-fail block w-full" role="alert">
                    {{ $message }}
                </p>
                @enderror
            </label>

            <button
                type="submit"
                class="button button-block bg-primary text-white mt-4">
                    {{ ctrans('texts.next') }}
            </button>
        </form>
    @endif

    @if($state['register_form'])
        @include('billing-portal.v3.authentication.register-form')
    @endif
</div>
