@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.setup'))

@push('head')
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.js" defer></script>
@endpush

@section('body')
    <div class="container mx-auto" x-data="{ greeting: true, start: false, database: false, mail: false, user: false, finish: false }">
        <form action="#" method="post">
            @csrf

            <div class="grid grid-cols-12 px-6">

                <div class="col-span-12 md:col-start-4 md:col-span-6 mt-4 md:mt-10">
                    <div id="greeting" x-transition:enter-start="opacity-0 transform scale-90" x-transition:enter-end="opacity-100 transform scale-100" x-transition:enter="transition ease-out duration-200" x-show="greeting" class="flex flex-col items-center justify-center">
                        <h1 class="text-2xl text-center">Invoice Ninja Setup</h1>
                        <p class="text-center">Welcome to next version of Invoice Ninja. We are so happy you decided to give it a try! Let's start!</p>
                        <div class="border-t w-full border-gray-100 mt-6 flex flex-col justify-center">
                            <button type="button" class="text-blue-500 hover:text-blue-600 mt-6" @click="{ greeting = false, start = true }">
                                Next step
                            </button>
                        </div>
                    </div>

                    <div id="start" x-transition:enter-start="opacity-0 transform scale-90" x-transition:enter-end="opacity-100 transform scale-100" x-transition:enter="transition ease-out duration-200" x-show="start" class="flex flex-col">
                        <h1 class="text-2xl text-center">Application</h1>
                        <p class="text-center">Let's store basic information about your Invoice Ninja!</p>

                        <div class="flex w-full items-center mt-4">
                            <label for="url" class="mr-4">URL:</label>
                            <input name="url" type="text" class="block w-full bg-gray-100 px-4 py-2 rounded">
                        </div>

                        <div class="flex items-center mt-4">
                            <label for="https" class="mr-4">HTTPS:</label>
                            <input type="checkbox" name="https" class="form-checkbox mr-1">
                            <span class="text-sm">Require</span>
                        </div>

                        <div class="flex items-center mt-4">
                            <label for="debug" class="mr-4">Debug:</label>
                            <input type="checkbox" name="debug" class="form-checkbox mr-1">
                            <span class="text-sm">Enable</span>
                        </div>

                        <div class="flex flex-col border-t w-full border-gray-100 mt-6">
                            <button type="button" class="text-blue-500 hover:text-blue-600 mt-6" @click="{ start = false, database = true }">
                                Next step
                            </button>
                            <button type="button" class="text-sm hover:text-blue-600 mt-2" @click="{ greeting = true, start = false }">
                                Go back
                            </button>
                        </div>
                    </div>

                    <div id="database-setup" x-transition:enter-start="opacity-0 transform scale-90" x-transition:enter-end="opacity-100 transform scale-100" x-transition:enter="transition ease-out duration-200" x-show="database">
                        <h1 class="text-2xl text-center">Database</h1>
                        <p class="text-center">To store data, we need database. Here's how you can create one.</p>

                        <pre id="create-database" class="mt-4 text-sm bg-gray-100 overflow-y-scroll px-4 py-2">
    -- Commands to create a MySQL database and user
    CREATE SCHEMA `ninja` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
    CREATE USER 'ninja'@'localhost' IDENTIFIED BY 'ninja';
    GRANT ALL PRIVILEGES ON `ninja`.* TO 'ninja'@'localhost';
    FLUSH PRIVILEGES;</pre>

                        <div class="flex w-full items-center mt-4">
                            <label for="url" class="mr-4 w-1/5 font-bold">Driver:</label>
                            <input readonly name="driver" type="text" class="block w-full bg-gray-100 px-4 py-2 rounded" value="MySQL">
                        </div>

                        <div class="flex w-full items-center mt-4">
                            <label for="host" class="mr-4 w-1/5 font-bold">Host:</label>
                            <input name="host" type="text" class="block w-full bg-gray-100 px-4 py-2 rounded">
                        </div>

                        <div class="flex w-full items-center mt-4">
                            <label for="username" class="mr-4 w-1/5 font-bold">Username:</label>
                            <input name="username" type="text" class="block w-full bg-gray-100 px-4 py-2 rounded">
                        </div>

                        <div class="flex w-full items-center mt-4">
                            <label for="password" class="mr-4 w-1/5">Password:</label>
                            <input name="password" type="password" class="block w-full bg-gray-100 px-4 py-2 rounded">
                        </div>

                        <button class="text-blue-600 hover:text-blue-700 mt-6 mb-2">Test connection</button>
                        <div class="alert alert-success">Success!</div>

                        <div class="flex flex-col border-t w-full border-gray-100 mt-6">
                            <button type="button" class="text-blue-500 hover:text-blue-600 mt-6" @click="{ database = false, mail = true }">
                                Next step
                            </button>
                            <button type="button" class="text-sm hover:text-blue-600 mt-2" @click="{ database = false, start = true }">
                                Go back
                            </button>
                        </div>
                    </div>

                    <div x-transition:enter-start="opacity-0 transform scale-90" x-transition:enter-end="opacity-100 transform scale-100" x-transition:enter="transition ease-out duration-300" id="email-settings" x-show="mail">
                        <h1 class="text-2xl text-center">E-mail</h1>
                        <p class="text-center">Awesome! Let's configure e-mail settings.</p>

                        <div class="flex w-full items-center mt-4">
                            <label for="host" class="mr-4 w-1/5 font-bold">Driver:</label>
                            <select name="smtp_driver" class="w-full form-select border-0 rounded bg-gray-100">
                                <option value="1">SMTP</option>
                            </select>
                        </div>

                        <div class="flex w-full items-center mt-4">
                            <label for="from_name" class="mr-4 w-1/5 font-bold">From name:</label>
                            <input name="from_name" type="text" class="block w-full bg-gray-100 px-4 py-2 rounded">
                        </div>

                        <div class="flex w-full items-center mt-4">
                            <label for="from_address" class="mr-4 w-1/5 font-bold">From address:</label>
                            <input name="from_address" type="email" class="block w-full bg-gray-100 px-4 py-2 rounded">
                        </div>

                        <div class="flex w-full items-center mt-4">
                            <label for="username" class="mr-4 w-1/5 font-bold">Username:</label>
                            <input name="smtp_username" type="text" class="block w-full bg-gray-100 px-4 py-2 rounded">
                        </div>

                        <div class="flex w-full items-center mt-4">
                            <label for="host" class="mr-4 w-1/5 font-bold">Host:</label>
                            <input name="smtp_host" type="text" class="block w-full bg-gray-100 px-4 py-2 rounded">
                        </div>

                        <div class="flex w-full items-center mt-4">
                            <label for="port" class="mr-4 w-1/5 font-bold">Port:</label>
                            <input name="port" type="text" class="block w-full bg-gray-100 px-4 py-2 rounded">
                        </div>

                        <div class="flex w-full items-center mt-4">
                            <label for="encryption" class="mr-4 w-1/5 font-bold">Encryption:</label>
                            <select name="encryption" class="w-full form-select border-0 rounded bg-gray-100">
                                <option value="1">TLS</option>
                            </select>
                        </div>

                        <div class="flex w-full items-center mt-4">
                            <label for="password" class="mr-4 w-1/5 font-bold">Password:</label>
                            <input name="smtp_password" type="password" class="block w-full bg-gray-100 px-4 py-2 rounded">
                        </div>

                        <button class="text-blue-600 hover:text-blue-700 mt-6 mb-2">Test connection</button>
                        <div class="alert alert-success">Success!</div>

                        <div class="flex flex-col border-t w-full border-gray-100 mt-6">
                            <button type="button" class="text-blue-500 hover:text-blue-600 mt-6" @click="{ mail = false, user = true }">
                                Next step
                            </button>
                            <button type="button" class="text-sm hover:text-blue-600 mt-2" @click="{ mail = false, database = true }">
                                Go back
                            </button>
                        </div>
                    </div>
                    <div x-transition:enter-start="opacity-0 transform scale-90" x-transition:enter-end="opacity-100 transform scale-100" x-transition:enter="transition ease-out duration-300" id="user" x-show="user">
                        <h1 class="text-2xl text-center">User</h1>
                        <p class="text-center">Almost done! Let's create first user!</p>

                        <div class="flex w-full items-center mt-4">
                            <label for="first_name" class="mr-4 w-1/5 font-bold">First name:</label>
                            <input name="first_name" type="string" class="block w-full bg-gray-100 px-4 py-2 rounded">
                        </div>

                        <div class="flex w-full items-center mt-4">
                            <label for="password" class="mr-4 w-1/5 font-bold">Last name:</label>
                            <input name="last_name" type="string" class="block w-full bg-gray-100 px-4 py-2 rounded">
                        </div>

                        <div class="flex w-full items-center mt-4">
                            <label for="password" class="mr-4 w-1/5 font-bold">E-mail:</label>
                            <input name="user_email" type="email" class="block w-full bg-gray-100 px-4 py-2 rounded">
                        </div>

                        <div class="flex w-full items-center mt-4">
                            <label for="password" class="mr-4 w-1/5 font-bold">Password:</label>
                            <input name="user_password" type="password" class="block w-full bg-gray-100 px-4 py-2 rounded">
                        </div>

                        <div class="flex flex-col border-t w-full border-gray-100 mt-6">
                            <button type="button" class="text-blue-500 hover:text-blue-600 mt-6" @click="{ user = false, finish = true }">
                                Next step
                            </button>
                            <button type="button" class="text-sm hover:text-blue-600 mt-2" @click="{ user = false, mail = true }">
                                Go back
                            </button>
                        </div>
                    </div>

                    <div x-transition:enter-start="opacity-0 transform scale-90" x-transition:enter-end="opacity-100 transform scale-100" x-transition:enter="transition ease-out duration-300" id="user" x-show="finish">
                        <h1 class="text-2xl text-center">Finish</h1>
                        <p class="text-center">.. one more thing.</p>

                        <div class="flex items-center mt-8">
                            <label for="debug" class="mr-4 w-1/4">Terms:</label>
                            <input type="checkbox" name="tos" class="form-checkbox mr-1">
                            <span class="text-sm">I agree to 
                                <a class="button-link" href="https://www.invoiceninja.com/self-hosting-terms-service/"> Terms of Service</a>
                            </span>
                        </div>

                        <div class="flex items-center mt-4">
                            <label for="debug" class="mr-4 w-1/4">Privacy policy:</label>
                            <input type="checkbox" name="privacy" class="form-checkbox mr-1">
                            <span class="text-sm">I agree to 
                                <a class="button-link" href="https://www.invoiceninja.com/self-hosting-privacy-data-control/"> Privacy Policy</a>
                            </span>
                        </div>

                        <div class="flex flex-col border-t w-full border-gray-100 mt-6">
                            <button type="submit" class="text-blue-500 hover:text-blue-600 mt-6">
                                Complete
                            </button>
                            <button type="button" class="text-sm hover:text-blue-600 mt-2" @click="{ finish = false, user = true }">
                                Go back
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection