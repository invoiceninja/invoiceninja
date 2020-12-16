<div class="bg-white shadow overflow-hidden rounded-lg mt-8">
    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
            {{ ctrans('texts.application_settings') }}
        </h3>
        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">
            {{ ctrans('texts.application_settings_label') }}
        </p>
    </div>
    <div>
        <dl>
            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.url') }}*
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="text" class="input w-full" name="url" required value="{{ old('url') }}">
                </dd>
            </div>
            <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.https') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="checkbox" class="form-checkbox mr-1" name="https"
                           {{ old('https') ? 'checked': '' }} checked>
                    <span>{{ ctrans('texts.require') }}</span>
                    <span class="text-gray-600 text-xs ml-2">({{ ctrans('texts.recommended_in_production') }})</span>
                </dd>
            </div>
            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.debug') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="checkbox" class="form-checkbox mr-1" name="debug" {{ old('debug') ? 'checked': '' }}>
                    <span>{{ ctrans('texts.enable') }}</span>
                    <span class="text-gray-600 text-xs ml-2">({{ ctrans('texts.enable_only_for_development') }})</span>
                </dd>
            </div>
            <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.reports') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="checkbox" class="form-checkbox mr-1"
                           name="send_logs" {{ old('send_logs' ? 'checked': '') }}>
                    <span>{{ ctrans('texts.send_fail_logs_to_our_server') }}</span>
                    <a class="button-link mt-1 block" href="https://www.invoiceninja.com/privacy-policy/">Read more
                        about how we use this.</a>
                </dd>
            </div>
            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    <button type="button" class="button button-primary bg-blue-600 py-2 px-3 text-xs" id="test-pdf">
                        {{ ctrans('texts.test_pdf') }}
                    </button>
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <div class="alert py-2 bg-gray-50" id="test-pdf-response"></div>
                </dd>
                <a target="_blank" class="block text-sm text-gray-900 leading-5 underline"
                   href="https://invoiceninja.github.io/selfhost.html#phantom-js">
                    {{ ctrans('texts.setup_phantomjs_note') }}
                </a>
            </div>
        </dl>
    </div>
</div>
