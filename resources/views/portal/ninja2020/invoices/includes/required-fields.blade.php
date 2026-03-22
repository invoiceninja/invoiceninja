<div
    style="display: none"
    id="displayRequiredFieldsModal"
    class="fixed bottom-0 inset-x-0 px-4 pb-4 sm:inset-0 sm:flex sm:items-center sm:justify-center"
    x-data="formValidation()"
>
    <div
        x-show="open"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 transition-opacity"
    >
        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
    </div>

    <div
        x-show="open"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        class="bg-white rounded-lg px-4 pt-5 pb-4 overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full sm:p-6"
    >
        <div class="sm:flex sm:items-start">
            <div
                class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"
            >
                <svg
                    class="h-6 w-6 text-red-600"
                    stroke="currentColor"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                    />
                </svg>
            </div>
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left"> 
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    {{ ctrans('texts.details') }}
                </h3>
                <div class="mt-2">
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
                        <label for="email" class="input-label">{{ ctrans('texts.email') }}</label>
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
                    <div class="col-span-6 sm:col-span-3" id="rff_city">
                        <label for="city" class="input-label">{{ ctrans('texts.city') }}</label>
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
                    <div class="col-span-6 sm:col-span-3" id="rff_postal_code">
                        <label for="postal_code" class="input-label">{{ ctrans('texts.postal_code') }}</label>
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

                </div>
            </div>
        </div>
        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse" >
            <div
                class="flex w-full rounded-md shadow-sm sm:ml-3 sm:w-auto">
                <button
                    type="button"
                    @@click="validateForm"
                    class="button button-primary bg-primary"
                >
                    {{ ctrans('texts.next_step') }}
                </button>
                <button
                    type="button"
                    id="rff-next-step"
                    class="hidden">
                </button>

            </div>
            <div
                class="mt-3 flex w-full rounded-md shadow-sm sm:mt-0 sm:w-auto"
                 
            >
                <button
                    type="button"
                    class="button button-secondary"
                    id="close-button"
                    @click="document.getElementById('displayRequiredFieldsModal').style.display = 'none';"
                >
                    {{ ctrans('texts.close') }}
                </button>
            </div>
        </div>
    </div>
</div>

 <script>

    function formValidation() {
        
        return {
            open: true,
            rff_last_name: '{{ auth()->guard('contact')->user()->last_name }}',
            rff_first_name: '{{ auth()->guard('contact')->user()->first_name }}',
            rff_email: '{{ auth()->guard('contact')->user()->email }}',
            rff_city: '{{ auth()->guard('contact')->user()->client->city }}',
            rff_postal_code: '{{ auth()->guard('contact')->user()->client->postal_code }}',
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

                if (!this.errors.rff_first_name && !this.errors.rff_last_name && !this.errors.email && !this.errors.rff_postal_code && !this.errors.rff_city) {
                    
                const next_rff = document.getElementById('rff-next-step');
                    next_rff.click();
                }
            },
        }
    }


    </script>

