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
    <form wire:submit="register(Object.fromEntries(new FormData($event.target)))" class="space-y-3">
        @csrf

        <div class="grid grid-cols-12 gap-4 mt-10">
            @if($subscription->company->client_registration_fields)
            @foreach($subscription->company->client_registration_fields as $field)
                @if($field['visible'])
                    <div class="col-span-12 md:col-span-6">
                        <section class="flex items-center">
                            <label
                                for="{{ $field['key'] }}"
                                class="input-label">
                                @if(in_array($field['key'], ['custom_value1','custom_value2','custom_value3','custom_value4']))
                                {{ (new App\Utils\Helpers())->makeCustomField($subscription->company->custom_fields, str_replace("custom_value","client", $field['key']))}}
                                @else
                                {{ ctrans("texts.{$field['key']}") }}
                                @endif
                            </label>

                            @if($field['required'])
                                <section class="text-red-400 ml-1 text-sm">*</section>
                            @endif
                        </section>

                        @if($field['key'] === 'email')
                            <input
                                id="{{ $field['key'] }}"
                                class="input w-full"
                                type="email"
                                name="{{ $field['key'] }}"
                                value="{{ old($field['key'], $this->email ?? '') }}"
                                 />
                        @elseif($field['key'] === 'password')
                            <input
                                id="{{ $field['key'] }}"
                                class="input w-full"
                                type="password"
                                name="{{ $field['key'] }}"
                                 />
                        @elseif($field['key'] === 'currency_id')
                            <select
                                id="currency_id"
                                class="input w-full form-select bg-white"
                                name="currency_id">
                                @foreach(App\Utils\TranslationHelper::getCurrencies() as $currency)
                                    <option
                                        {{ $currency->id == $subscription->company->settings->currency_id ? 'selected' : null }} value="{{ $currency->id }}">
                                        {{ $currency->name }}
                                    </option>
                                @endforeach
                            </select>
                        @elseif($field['key'] === 'country_id')
                            <select
                                id="shipping_country"
                                class="input w-full form-select bg-white"
                                name="country_id">
                                    <option value="none"></option>
                                @foreach(App\Utils\TranslationHelper::getCountries() as $country)
                                    <option
                                        {{ $country == isset(auth()->user()->client->shipping_country->id) ? 'selected' : null }} value="{{ $country->id }}">
                                        {{ $country->iso_3166_2 }}
                                        ({{ $country->name }})
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input
                                id="{{ $field['key'] }}"
                                class="input w-full"
                                name="{{ $field['key'] }}"
                                value="{{ old($field['key']) }}"
                                 />
                        @endif

                        @error($field['key'])
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    @if($field['key'] === 'password')
                        <div class="col-span-12 md:col-span-6">
                            <section class="flex items-center">
                                <label
                                    for="password_confirmation"
                                    class="input-label">
                                    {{ ctrans('texts.password_confirmation') }}
                                </label>

                                @if($field['required'])
                                    <section class="text-red-400 ml-1 text-sm">*</section>
                                @endif
                            </section>

                            <input
                                id="password_confirmation"
                                type="password"
                                class="input w-full"
                                name="password_confirmation"
                                 />
                        </div>
                    @endif
                @endif
            @endforeach
            @endif
        </div>

        <button 
            type="submit"
            class="button button-block bg-primary text-white mt-4">
                {{ ctrans('texts.next') }}
        </button>
    </form>
    @endif
</div>
