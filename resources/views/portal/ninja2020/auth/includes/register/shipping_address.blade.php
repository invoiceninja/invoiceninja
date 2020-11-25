 <!-- Client shipping address -->
 <h3 class="text-lg font-medium leading-6 text-gray-900 mt-8">{{ ctrans('texts.shipping_address') }}</h3>

 <p class="mt-1 text-sm leading-5 text-gray-500">
     {{ ctrans('texts.enter_your_shipping_address') }}
 </p>

 <div class="shadow overflow-hidden rounded mt-4">
     <div class="px-4 py-5 bg-white sm:p-6">
         <div class="grid grid-cols-6 gap-6">
             <div class="col-span-6 sm:col-span-4">
                 <label for="shipping_address1" class="input-label">{{ ctrans('texts.shipping_address1') }}</label>
                 <input id="shipping_address1" class="input w-full {{ in_array('shipping_address1', (array) session('missing_required_fields')) ? 'border border-red-400' : '' }}" name="shipping_address1" />
                 @error('shipping_address1')
                    <div class="validation validation-fail">
                        {{ $message }}
                    </div>
                 @enderror
             </div>
             <div class="col-span-6 sm:col-span-3">
                 <label for="shipping_address2" class="input-label">{{ ctrans('texts.shipping_address2') }}</label>
                 <input id="shipping_address2 {{ in_array('shipping_address2', (array) session('missing_required_fields')) ? 'border border-red-400' : '' }}" class="input w-full" name="shipping_address2" />
                 @error('shipping_address2')
                    <div class="validation validation-fail">
                        {{ $message }}
                    </div>
                 @enderror
             </div>
             <div class="col-span-6 sm:col-span-3">
                 <label for="shipping_city" class="input-label">{{ ctrans('texts.shipping_city') }}</label>
                 <input id="shipping_city" class="input w-full {{ in_array('shipping_city', (array) session('missing_required_fields')) ? 'border border-red-400' : '' }}" name="shipping_city" />
                 @error('shipping_city')
                    <div class="validation validation-fail">
                        {{ $message }}
                    </div>
                 @enderror
             </div>
             <div class="col-span-6 sm:col-span-2">
                 <label for="shipping_state" class="input-label">{{ ctrans('texts.shipping_state') }}</label>
                 <input id="shipping_state" class="input w-ful {{ in_array('shipping_state', (array) session('missing_required_fields')) ? 'border border-red-400' : '' }}l" name="shipping_state" />
                 @error('shipping_state')
                    <div class="validation validation-fail">
                        {{ $message }}
                    </div>
                 @enderror
             </div>
             <div class="col-span-6 sm:col-span-2">
                 <label for="shipping_postal_code" class="input-label">{{ ctrans('texts.shipping_postal_code') }}</label>
                 <input id="shipping_postal_code" class="input w-full {{ in_array('shipping_postal_code', (array) session('missing_required_fields')) ? 'border border-red-400' : '' }}" name="shipping_postal_code" />
                 @error('shipping_postal_code')
                    <div class="validation validation-fail">
                        {{ $message }}
                    </div>
                 @enderror
             </div>
             <div class="col-span-4 sm:col-span-2">
                 <label for="shipping_country" class="input-label">{{ ctrans('texts.shipping_country') }}</label>
                 <select id="shipping_country" class="input w-full form-select {{ in_array('shipping_country', (array) session('missing_required_fields')) ? 'border border-red-400' : '' }}" name="shipping_country">
                     @foreach(App\Utils\TranslationHelper::getCountries() as $country)
                        <option {{ $country == isset(auth()->user()->client->shipping_country->id) ? 'selected' : null }} value="{{ $country->id }}">
                            {{ $country->iso_3166_2 }}
                            ({{ $country->name }})
                        </option>
                     @endforeach
                 </select>
                 @error('country')
                    <div class="validation validation-fail">
                        {{ $message }}
                    </div>
                 @enderror
             </div>
         </div>
     </div>
 </div>