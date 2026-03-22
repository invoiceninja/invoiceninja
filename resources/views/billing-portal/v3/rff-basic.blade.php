<div>
    <div>
        <form wire:submit="handleSubmit">
            @csrf

            <label for="contact_first_name">
                <span class="input-label">{{ ctrans('texts.first_name') }}</span>
                <input wire:model="contact_first_name" type="text" class="input w-full" />

                @error('contact_first_name')
                <p class="validation validation-fail block w-full" role="alert">
                    {{ $message }}
                </p>
                @enderror
            </label>

            <label for="contact_last_name">
                <span class="input-label">{{ ctrans('texts.last_name') }}</span>
                <input wire:model="contact_last_name" type="text" class="input w-full" />

                @error('contact_last_name')
                <p class="validation validation-fail block w-full" role="alert">
                    {{ $message }}
                </p>
                @enderror
            </label>

            <label for="contact_email">
                <span class="input-label">{{ ctrans('texts.email_address') }}</span>
                <input wire:model="contact_email" type="email" class="input w-full" />

                @error('contact_email')
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
    </div>
</div>
