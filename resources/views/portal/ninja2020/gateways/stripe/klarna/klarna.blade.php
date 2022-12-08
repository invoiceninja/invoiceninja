<div id="stripe--payment-container">
    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.name')])
        <div class="form-group mb-[10px]">
            <input class="input w-full" id="klarna-name" type="text" placeholder="{{ ctrans('texts.bank_account_holder') }}" value="{{ $gateway->client->present()->name()}}" required>
        </div>
        <div class="form-group mb-[10px]">
            <input
                    type="text"
                    class="input w-full m-0"
                    id="address2"
                    placeholder="{{ ctrans('texts.address2') }}"
                    name="address2"
                    value="{{$gateway->client->address2}}"
            />
        </div>
        <div class="form-group mb-[10px]">
            <input
                    type="text"
                    class="input w-full m-0"
                    id="address1"
                    placeholder="{{ ctrans('texts.address1') }}"
                    name="address1"
                    value="{{$gateway->client->address1}}"
            />
        </div>

        <div
                class="flex form-group flex justify-center gap-[13px] mb-[10px]"
        >
            <div class="w-full gap-x-2 md:w-1/3">
                <div class="form-group">
                    <input
                            type="text"
                            class="input w-full m-0"
                            id="city"
                            placeholder="{{ ctrans('texts.city') }}"
                            name="city"
                            value="{{$gateway->client->city}}"
                    />
                </div>
            </div>
            <div class="w-full gap-x-2 md:w-1/3">
                <div class="form-group">
                    <input
                            type="text"
                            class="input w-full m-0"
                            id="state"
                            placeholder="{{ ctrans('texts.state') }}"
                            name="state"
                            value="{{$gateway->client->state}}"
                    />
                </div>
            </div>
            <div class="w-full gap-x-2 md:w-1/3">
                <div class="form-group">
                    <input
                            type="text"
                            class="input w-full m-0"
                            id="postal_code"
          placeholder="{{ ctrans('texts.postal_code') }}"
                            name="postal_code"
          value="{{$gateway->client->postal_code}}"
                    />
                </div>
            </div>
        </div>

    @endcomponent
</div>
