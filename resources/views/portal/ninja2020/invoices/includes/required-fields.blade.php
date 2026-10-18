<div x-data="formValidation()">
@component('portal.ninja2020.components.dialog', ['id' => 'displayRequiredFieldsModal', 'title' => ctrans('texts.details')])
                    @if(strlen(auth()->guard('contact')->user()->first_name) === 0)
                    <div class="col-span-6 sm:col-span-3">
                        <label for="rff_first_name" class="input-label">{{ ctrans('texts.first_name') }}</label>
                        <input 
                            id="rff_first_name" 
                            class="input w-full" 
                            name="rff_first_name" 
                            value="{{ auth()->guard('contact')->user()->first_name }}"
                            x-model="rff_first_name"
                            @blur="validateFirstName()"
                            :class="{ 'border-red-500': errors.rff_first_name }"
                        />
                        <span x-show="errors.rff_first_name" class="validation validation-fail block w-full" role="alert" x-text="errors.rff_first_name"></span>
                    </div>
                    @endif

                    @if(strlen(auth()->guard('contact')->user()->last_name) === 0)
                    <div class="col-span-6 sm:col-span-3">
                        <label for="rff_last_name" class="input-label">{{ ctrans('texts.last_name') }}</label>
                        <input 
                            id="rff_last_name" 
                            class="input w-full" 
                            name="rff_last_name" 
                            x-model="rff_last_name"
                            @blur="validateLastName()"
                            :class="{ 'border-red-500': errors.rff_last_name }"
                        />
                        <span x-show="errors.rff_last_name" class="validation validation-fail block w-full" role="alert" x-text="errors.rff_last_name"></span>

                    </div>
                    @endif

                    @if(strlen(auth()->guard('contact')->user()->email) === 0)
                    <div class="col-span-6 sm:col-span-3">
                        <label for="rff_email" class="input-label">{{ ctrans('texts.email') }}</label>
                        <input 
                            id="rff_email" 
                            class="input w-full" 
                            name="rff_email"  
                            x-model="rff_email"
                            @blur="validateEmail()"
                            :class="{ 'border-red-500': errors.rff_email }"    
                            />
                            <span x-show="errors.rff_email" class="validation validation-fail block w-full" role="alert" x-text="errors.rff_email"></span>

                    </div>
                    @endif

                    @if(strlen(auth()->guard('contact')->user()->client->city) === 0)
                    <div class="col-span-6 sm:col-span-3" >
                        <label for="rff_city" class="input-label">{{ ctrans('texts.city') }}</label>
                        <input 
                            id="rff_city" 
                            class="input w-full" 
                            name="rff_city"  
                            x-model="rff_city"
                            @blur="validateCity()"
                            :class="{ 'border-red-500': errors.rff_city }"    
                            />
                            <span x-show="errors.rff_city" class="validation validation-fail block w-full" role="alert" x-text="errors.rff_city"></span>
                    
                    </div>
                    @endif

                    @if(strlen(auth()->guard('contact')->user()->client->postal_code) === 0)
                    <div class="col-span-6 sm:col-span-3" >
                        <label for="rff_postal_code" class="input-label">{{ ctrans('texts.postal_code') }}</label>
                        <input 
                            id="rff_postal_code" 
                            class="input w-full" 
                            name="rff_postal_code"  
                            x-model="rff_postal_code"
                            @blur="validatePostalCode()"
                            :class="{ 'border-red-500': errors.rff_postal_code }"    
                            />
                            <span x-show="errors.rff_postal_code" class="validation validation-fail block w-full" role="alert" x-text="errors.rff_postal_code"></span>
                    
                    </div>
                    @endif

    @slot('actions')
        <button type="button" @click="validateForm()" class="button button-primary bg-primary">{{ ctrans('texts.next_step') }}</button>
        <button type="button" id="rff-next-step" class="hidden"></button>
        <button type="button" data-dialog-close id="close-required-fields-button" class="button button-secondary">{{ ctrans('texts.close') }}</button>
    @endslot
@endcomponent
</div>
 <script>

    function formValidation() {
        
        return {
            rff_last_name: @js(auth()->guard('contact')->user()->last_name),
            rff_first_name: @js(auth()->guard('contact')->user()->first_name),
            rff_email: @js(auth()->guard('contact')->user()->email),
            rff_city: @js(auth()->guard('contact')->user()->client->city),
            rff_postal_code: @js(auth()->guard('contact')->user()->client->postal_code),
            errors: {
                rff_first_name: '',
                rff_last_name: '',
                rff_city: '',
                rff_postal_code: '',
                rff_email: ''
            },

            validateFirstName() {
                this.errors.rff_first_name = this.rff_first_name.trim() === '' ? '{{ ctrans('texts.first_name') }}' + ' ' + '{{ ctrans('texts.required') }}' : '';
            },

            validateLastName() {
                this.errors.rff_last_name = this.rff_last_name.trim() === '' ? '{{ ctrans('texts.last_name') }}' + ' ' + '{{ ctrans('texts.required') }}' : '';
            },

            validateEmail() {
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                this.errors.rff_email = !emailPattern.test(this.rff_email.trim())  ? '{{ ctrans('texts.provide_email') }}' : '';
            },

            validatePostalCode() {
                this.errors.rff_postal_code = this.rff_postal_code.trim() === '' ? '{{ ctrans('texts.postal_code') }}' + ' ' + '{{ ctrans('texts.required') }}' : '';
            },

            validateCity() {
                this.errors.rff_city = this.rff_city.trim() === '' ? '{{ ctrans('texts.city') }}' + ' ' + '{{ ctrans('texts.required') }}' : '';
            },

            validateForm() {
                
                this.validateFirstName();
                this.validateLastName();
                this.validateEmail();
                this.validateCity();
                this.validatePostalCode();

                if (!this.errors.rff_first_name && !this.errors.rff_last_name && !this.errors.rff_email && !this.errors.rff_postal_code && !this.errors.rff_city) {
                    
                const next_rff = document.getElementById('rff-next-step');
                    next_rff.click();
                }
            },
        }
    }


    </script>

