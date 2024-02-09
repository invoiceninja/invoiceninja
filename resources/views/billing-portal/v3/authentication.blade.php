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

    @if($state['login_form'])
    <form wire:submit="handlePassword" class="space-y-3">
        @csrf

        <div>
            <span class="input-label">{{ ctrans('texts.email_address') }}</span>
            <input wire:model="email" type="email" class="input w-full" />

            @error('email')
            <p class="validation validation-fail block w-full" role="alert">
                {{ $message }}
            </p>
            @enderror
        </div>

        <div>
            <span class="input-label">{{ ctrans('texts.password') }}</span>
            <input wire:model="password" type="password" class="input w-full" />

            @error('password')
            <p class="validation validation-fail block w-full" role="alert">
                {{ $message }}
            </p>
            @enderror
        </div>

        <button 
            type="submit"
            class="button button-block bg-primary text-white mt-4">
                {{ ctrans('texts.next') }}
        </button>
    </form>
    @endif

    @if($state['otp_form'])
    <form wire:submit="handleOtp" class="space-y-3">
        @csrf

        <div>
            <span class="input-label">{{ ctrans('texts.code') }}</span>
            <input wire:model="otp" type="text" class="input w-full" />

            @error('otp')
            <p class="validation validation-fail block w-full" role="alert">
                {{ $message }}
            </p>
            @enderror
        </div>

        <button 
            type="submit"
            class="button button-block bg-primary text-white mt-4">
                {{ ctrans('texts.next') }}
        </button>
    </form>
    @endif
</div>
