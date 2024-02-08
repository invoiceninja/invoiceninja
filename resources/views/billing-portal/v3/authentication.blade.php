<div>
    @if (session()->has('message'))
        @component('portal.ninja2020.components.message')
            {{ session('message') }}
        @endcomponent
    @endif

    @if($state['code'] === false)
    <form wire:submit="authenticate">
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
    @else
        <p>We have sent a code to {{ $email }} enter this code to proceed.</p>
    @endif
</div>
