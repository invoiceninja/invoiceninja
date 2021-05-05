    <div class="flex flex-col justify-center items-center mt-10">

     <form wire:submit.prevent="submit">
        @csrf
        @method('POST')
        <div class="shadow overflow-hidden rounded">
            <div class="px-4 py-5 bg-white sm:p-6">
                <div class="grid grid-cols-6 gap-6">
                    <div class="col-span-6 sm:col-span-3">
                        <label for="first_name" class="input-label">@lang('texts.first_name')</label>
                        <input id="first_name" class="input w-full" name="first_name" wire:model.defer="first_name" />
                        @error('first_name')
                        <div class="validation validation-fail">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>

                    <div class="col-span-6 sm:col-span-3">
                        <label for="last_name" class="input-label">@lang('texts.last_name')</label>
                        <input id="last_name" class="input w-full" name="last_name" wire:model.defer="last_name" />
                        @error('last_name')
                        <div class="validation validation-fail">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <label for="email_address" class="input-label">@lang('texts.email_address')</label>
                        <input id="email_address" class="input w-full" type="email" name="email" wire:model.defer="email" disabled="true" />
                        @error('email')
                        <div class="validation validation-fail">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <label for="company_name" class="input-label">@lang('texts.company_name')</label>
                        <input id="company_name" class="input w-full" name="company_name" wire:model.defer="company_name" />
                        @error('company_name')
                        <div class="validation validation-fail">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <label for="country" class="input-label">@lang('texts.country')</label>
                    
                        <div class="radio">
                        <input class="form-radio mr-2" type="radio" value="US" name="country" checked>
                        <span>{{ ctrans('texts.country_United States') }}</span>
                        </div>

                        <div class="radio">
                        <input class="form-radio mr-2" type="radio" value="CA" name="country">
                        <span>{{ ctrans('texts.country_Canada') }}</span>
                        </div>

                        <div class="radio">
                        <input class="form-radio mr-2" type="radio" value="GB" name="country">
                        <span>{{ ctrans('texts.country_United Kingdom') }}</span>
                        </div>

                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <label for="country" class="input-label">@lang('texts.ach')</label>
                        <div class="checkbox">
                        <input class="switch-input" type="checkbox" name="ach">
                        <span>{{ ctrans('texts.enable_ach')}}</span>
                        </div>
                    </div>

                    <div class="col-span-6 sm:col-span-4">
                        <label for="country" class="input-label"></label>
                        <div class="checkbox">
                        <input class="switch-input" type="checkbox" name="wepay_payment_tos_agree">
                        <span>{!! ctrans('texts.wepay_payment_tos_agree', ['terms' => $terms, 'privacy_policy' => $privacy_policy]) !!}</span>
                        </div>
                    </div>

                </div>
            </div>
            <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                <button class="button button-primary bg-primary">{{ $saved }}</button>
            </div>
        </div>
    </form>

</div>