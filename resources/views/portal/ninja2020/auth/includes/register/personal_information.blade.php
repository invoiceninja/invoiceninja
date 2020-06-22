<!-- Personal info, first name, last name, e-mail address .. -->
<h3 class="text-lg font-medium leading-6 text-gray-900 mt-8">{{ ctrans('texts.profile') }}</h3>

<p class="mt-1 text-sm leading-5 text-gray-500">
    {{ ctrans('texts.client_information_text') }}
</p>

<div class="shadow overflow-hidden rounded mt-4">
    <div class="px-4 py-5 bg-white sm:p-6">
        <div class="grid grid-cols-6 gap-6">
            <div class="col-span-6 sm:col-span-3">
                <section class="flex items-center">
                    <label for="first_name" class="input-label">{{ ctrans('texts.first_name') }}</label>
                    <section class="text-red-400 ml-1 text-sm">*</section>
                </section>
                <input id="first_name" class="input w-full" name="first_name" />
                @error('first_name')
                    <div class="validation validation-fail">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div class="col-span-6 sm:col-span-3">
                <section class="flex items-center">
                    <label for="last_name" class="input-label">{{ ctrans('texts.last_name') }}</label>
                    <section class="text-red-400 ml-1 text-sm">*</section>
                </section>
                <input id="last_name" class="input w-full" name="last_name" />
                @error('last_name')
                    <div class="validation validation-fail">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div class="col-span-6 sm:col-span-4">
                <section class="flex items-center">
                    <label for="email_address" class="input-label">{{ ctrans('texts.email_address') }}</label>
                    <section class="text-red-400 ml-1 text-sm">*</section>
                </section>
                <input id="email_address" class="input w-full" type="email" name="email" />
                @error('email')
                    <div class="validation validation-fail">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div class="col-span-6 sm:col-span-4">
                <section class="flex items-center">
                    <label for="phone" class="input-label">{{ ctrans('texts.phone') }}</label>
                    <section class="text-red-400 ml-1 text-sm">*</section>
                </section>
                <input id="phone" class="input w-full" name="phone" />
                @error('phone')
                <div class="validation validation-fail">
                    {{ $message }}
                </div>
                @enderror
            </div>

            <div class="col-span-6 sm:col-span-6 lg:col-span-3">
                <section class="flex items-center">
                    <label for="password" class="input-label">{{ ctrans('texts.password') }}</label>
                    <section class="text-red-400 ml-1 text-sm">*</section>
                </section>
                <input id="password" class="input w-full" name="password" type="password" />
                @error('password')
                <div class="validation validation-fail">
                    {{ $message }}
                </div>
                @enderror
            </div>

            <div class="col-span-6 sm:col-span-3 lg:col-span-3">
                <section class="flex items-center">
                    <label for="password_confirmation" class="input-label">{{ ctrans('texts.confirm_password') }}</label>
                    <section class="text-red-400 ml-1 text-sm">*</section>
                </section>
                <input id="state" class="input w-full" name="password_confirmation" type="password" />
                @error('password_confirmation')
                    <div class="validation validation-fail">
                        {{ $message }}
                    </div>
                @enderror
            </div>
        </div>
    </div>
</div>