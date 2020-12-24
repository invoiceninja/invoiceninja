<div class="mt-8 overflow-hidden bg-white rounded-lg shadow">
    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
        <h3 class="text-lg font-medium leading-6 text-gray-900">
            {{ ctrans('texts.application_settings') }}
        </h3>
        <p class="max-w-2xl mt-1 text-sm leading-5 text-gray-500">
            {{ ctrans('texts.application_settings_label') }}
        </p>
    </div>
    <div>
        <dl>
            <div class="px-4 py-5 bg-gray-50 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.url') }}*
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="text" class="w-full input" name="url" required value="{{ old('url') }}">
                </dd>
            </div>
            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.https') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="checkbox" class="mr-1 form-checkbox" name="https"
                           {{ old('https') ? 'checked': '' }} checked>
                    <span>{{ ctrans('texts.require') }}</span>
                    <span class="ml-2 text-xs text-gray-600">({{ ctrans('texts.recommended_in_production') }})</span>
                </dd>
            </div>
            <div class="px-4 py-5 bg-gray-50 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.debug') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="checkbox" class="mr-1 form-checkbox" name="debug" {{ old('debug') ? 'checked': '' }}>
                    <span>{{ ctrans('texts.enable') }}</span>
                    <span class="ml-2 text-xs text-gray-600">({{ ctrans('texts.enable_only_for_development') }})</span>
                </dd>
            </div>
            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.reports') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="checkbox" class="mr-1 form-checkbox"
                           name="send_logs" {{ old('send_logs' ? 'checked': '') }}>
                    <span>{{ ctrans('texts.send_fail_logs_to_our_server') }}</span>
                    <a class="block mt-1 text-xs underline button-link" href="https://www.invoiceninja.com/privacy-policy/">Read more
                        about how we use this.</a>
                </dd>
            </div>
            <div class="px-4 py-5 bg-gray-50 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.application_key') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="checkbox" class="mr-1 form-checkbox"
                           name="generate_app_key" checked />
                    <span>{{ ctrans('texts.generate_app_key_label') }}</span>
                    <span class="ml-2 text-xs text-gray-600">({{ ctrans('texts.recommended') }})</span>
                </dd>
            </div>
            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm font-medium leading-5 text-gray-500">
                    <button type="button" class="px-3 py-2 text-xs bg-blue-600 button button-primary" id="test-pdf">
                        {{ ctrans('texts.test_pdf') }}
                    </button>
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <div class="py-2 bg-white alert" id="test-pdf-response"></div>
                </dd>
                <a target="_blank" class="block text-sm leading-5 text-gray-900 underline"
                   href="https://invoiceninja.github.io/selfhost.html#phantom-js">
                    {{ ctrans('texts.setup_phantomjs_note') }}
                </a>
            </div>
        </dl>
    </div>
</div>
