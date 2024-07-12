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
                    <label for="https">
                        <input type="checkbox" class="form-checkbox mr-1" name="https"
                            id="https" {{ old('https') ? 'checked': '' }} checked>
                        <span>{{ ctrans('texts.require') }}</span>
                        <span class="text-gray-600 text-xs ml-2">({{ ctrans('texts.recommended_in_production') }})</span>
                    </label>
                </dd>
            </div>
            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.reports') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <label for="send_logs">
                        <input type="checkbox" class="form-checkbox mr-1"
                            name="send_logs" id="send_logs" {{ old('send_logs' ? 'checked': '') }}>
                        <span>{{ ctrans('texts.send_fail_logs_to_our_server') }}</span>
                    </label>
                    <a class="button-link mt-1 block" target="_blank" href="https://www.invoiceninja.com/privacy-policy/">Read more
                        about how we use this.</a>
                </dd>
            </div>
        </dl>
    </div>
</div>
<div class="bg-white shadow overflow-hidden rounded-lg mt-6">
    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
            {{ ctrans('texts.database_connection') }}
        </h3>
        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">
        </p>
    </div>
    <div>
        <dl>
            @if (! config('ninja.preconfigured_install'))
            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    You can use following commands to create user & database.
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <details>
                        <summary class="cursor-pointer focus:outline-none">Show code</summary>
                        <pre class="text-sm overflow-y-scroll bg-gray-100 p-4">
-- Commands to create a MySQL database and user
CREATE SCHEMA `db-ninja-01` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
CREATE USER 'ninja'@'localhost' IDENTIFIED BY 'ninja';
GRANT ALL PRIVILEGES ON `db-ninja-01`.* TO 'ninja'@'localhost';
FLUSH PRIVILEGES;
                        </pre>
                    </details>
                </dd>
            </div>
            <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.driver') }}
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="text" class="border-none" name="db_driver" value="MySQL" readonly>
                </dd>
            </div>
            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.host') }}*
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="text" class="input w-full" name="db_host" required value="{{ old('host') ?: 'localhost'}}">
                </dd>
            </div>
            <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.port') }}*
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="text" class="input w-full" name="db_port" required value="{{ old('db_port') ?: '3306'}}">
                </dd>
            </div>
            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.database') }}*
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="text" class="input w-full" name="db_database" required value="{{ old('database') ?: 'db-ninja-01'}}">
                </dd>
            </div>
            <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.username') }}*
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="text" class="input w-full" name="db_username" required value="{{ old('db_username') ?: 'ninja' }}">
                </dd>
            </div>
            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.password') }}
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <input type="password" class="input w-full" name="db_password" value="{{ old('db_password') ?: 'ninja' }}">
                </dd>
            </div>
            @endif
            <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    <button type="button" class="button button-primary bg-blue-600 py-2 px-3 text-xs" id="test-db-connection">
                        {{ ctrans('texts.test_connection') }}
                    </button>
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    <div class="alert py-2 bg-white" id="database-response"></div>
                </dd>
            </div>
        </dl>
    </div>
</div>
