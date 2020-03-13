<div class="hidden md:flex md:flex-shrink-0">
    <div class="flex flex-col w-64">
        <div class="flex items-center h-16 flex-shrink-0 px-4 bg-blue-900">
            <a href="{{ route('client.dashboard') }}">
                <img class="h-6 w-auto"
                     src="{!! $settings->company_logo ?: 'https://www.invoiceninja.com/wp-content/themes/invoice-ninja/images/logo.png' !!}"
                     alt="{{ config('app.name') }}"/>
            </a>
        </div>
        <div class="h-0 flex-1 flex flex-col overflow-y-auto">
            <nav class="flex-1 py-4 bg-blue-800">
                @foreach($sidebar as $row)
                    <a class="group flex items-center p-4 text-sm leading-5 font-medium text-white bg-blue-800 hover:bg-blue-900 focus:outline-none focus:bg-blue-900 transition ease-in-out duration-150 {{ isActive($row['url']) }}"
                       href="{{ route($row['url']) }}">
                        <img src="{{ asset('images/svg/' . $row['icon'] . '.svg') }}" class="w-5 h-5 fill-current text-white mr-3" alt=""/>
                        {{ $row['title'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </div>
</div>

