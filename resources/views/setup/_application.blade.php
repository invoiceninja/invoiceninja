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
                    <input
                        type="url" class="input w-full" name="url" placeholder="https://example.com"
                        pattern="https?://.*" size="45" value="{{ old('url', 'https://') }}" required>
                        <small>(including http:// or https://)</small>
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
                    {{ ctrans('texts.reports') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="checkbox" class="form-checkbox mr-1"
                           name="send_logs" {{ old('send_logs' ? 'checked': '') }}>
                    <span>{{ ctrans('texts.send_fail_logs_to_our_server') }}</span>
                    <a class="button-link mt-1 block" target="_blank" href="https://www.invoiceninja.com/privacy-policy/">Read more
                        about how we use this.</a>
                </dd>
            </div>
            <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    <button type="button" class="button button-primary bg-blue-600 py-2 px-3 text-xs" id="test-pdf">
                        {{ ctrans('texts.test_pdf') }}
                    </button>
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <div class="alert py-2 bg-white" id="test-pdf-response"></div>
                </dd>
            </div>
        </dl>
    </div>
</div>
