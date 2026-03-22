<div class="bg-white shadow overflow-hidden rounded-lg mt-6 hidden" id="mail-wrapper" x-data="{ option: 'log' }">
    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
            {{ ctrans('texts.email_settings') }}
        </h3>
        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">
            Let's configure e-mail settings.
        </p>
    </div>
    <div>
        <dl>
            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.driver') }}
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <select name="mail_driver" class="input w-full form-select" x-model="option">
                        <option value="log">Log</option>
                        <option value="smtp">SMTP</option>
                        <option value="sendmail">Sendmail</option>
                    </select>
                </dd>
            </div>
            <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center" x-show="option != 'log'">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.from_name') }}
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="text" class="input w-full" name="mail_name" value="{{ old('mail_name') }}">
                </dd>
            </div>
            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center"  x-show="option != 'log'">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.from_address') }}
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="email" class="input w-full" name="mail_address" value="{{ old('mail_address') }}">
                </dd>
            </div>
            <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center" x-show="option != 'log'">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.username') }}
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="text" class="input w-full" name="mail_username" value="{{ old('mail_username') }}" x-show="option != 'log'">
                </dd>
            </div>
            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center" x-show="option != 'log'">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.host') }}
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="text" class="input w-full" name="mail_host" value="{{ old('mail_host') }}">
                </dd>
            </div>
            <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center" x-show="option != 'log'">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.port') }}
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="text" class="input w-full" name="mail_port" value="{{ old('mail_port') }}">
                </dd>
            </div>
            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center" x-show="option != 'log'">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.encryption') }}
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <select name="encryption" class="input w-full form-select">
                        <option value="tls">STARTTLS</option>
                        <option value="ssl">SSL/TLS</option>
                    </select>
                </dd>
            </div>
            <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center" x-show="option != 'log'">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.password') }}
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="password" class="input w-full" name="mail_password">
                </dd>
            </div>
            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    <button type="button" class="button button-primary bg-blue-600 py-2 px-3 text-xs" id="test-smtp-connection">
                        {{ ctrans('texts.send_test_email') }}
                    </button>
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <div class="alert py-2 bg-gray-50" id="smtp-response"></div>
                </dd>
            </div>
        </dl>
    </div>
</div>
