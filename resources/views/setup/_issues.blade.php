<div class="bg-white shadow overflow-hidden rounded-lg mt-8">
    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
            {{ ctrans('texts.oops_issues') }}
        </h3>
        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">
            {{ ctrans('texts.satisfy_requirements') }}
        </p>
    </div>
    <div>
        <dl>
            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.required_extensions') }}
                </dt>
                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    @foreach($check['extensions'] as $extension)
                    <span class="alert py-1 {{ $extension[key($extension)] == true ? 'alert-success' : 'alert-failure' }} block w-full flex justify-between items-center">
                        <span>{{ key($extension) }}</span>
                        <span>{{ $extension[key($extension)] == true ? '✔' : '❌' }}</span>
                    </span>
                    @endforeach
                </dd>
            </div>
            <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.php_version') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    @if($check['php_version']['is_okay'])
                    <span class="alert alert-success block flex justify-between items-center">
                        <span>{{ strtoupper(ctrans('texts.ok')) }}</span>
                        <span>✔</span>
                    </span>
                    @else
                    <span class="alert block">
                        {{ ctrans('texts.minumum_php_version') }}: {{ $check['php_version']['minimum_php_version'] }}
                    </span>
                    <span class="alert alert-failure block flex justify-between items-center">
                        <span>{{ ctrans('texts.current') }}: {{ $check['php_version']['current_php_version'] }}</span>
                        <span>❌</span>
                    </span>
                    @endif
                </dd>
            </div>
            <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:flex sm:items-center">
                <dt class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.writable_env_file') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    @if($check['env_writable'])
                    <span class="alert alert-success block flex justify-between items-center">
                        <span>{{ strtoupper(ctrans('texts.ok')) }}</span>
                        <span>✔</span>
                    </span>
                    @else
                    <span class="alert alert-failure block flex justify-between items-center">
                        <span>{{ ctrans('texts.env_not_writable') }}</span>
                        <span>❌</span>
                    </span>
                    @endif
                </dd>
            </div>
        </dl>
    </div>
</div>