<div class="md:hidden">
    <div @click="sidebarOpen = false"
         class="fixed inset-0 z-30 bg-gray-600 opacity-0 pointer-events-none transition-opacity ease-linear duration-300"
         :class="{'opacity-75 pointer-events-auto': sidebarOpen, 'opacity-0 pointer-events-none': !sidebarOpen}"></div>
    <div
        class="fixed inset-y-0 left-0 flex flex-col z-40 max-w-xs w-full pt-5 pb-4 bg-blue-800 transform ease-in-out duration-300 -translate-x-full"
        :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}">
        <div class="absolute top-0 right-0 -mr-14 p-1">
            <button x-show="sidebarOpen" @click="sidebarOpen = false"
                    class="flex items-center justify-center h-12 w-12 rounded-full focus:outline-none focus:bg-gray-600">
                <svg class="h-6 w-6 text-white" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="flex-shrink-0 flex items-center px-4">
            <img class="h-6 w-auto"
                 src="{!! $settings->company_logo ?: asset('images/invoiceninja-white-logo.png') !!}"
                 alt="{{ config('app.name') }}"/>
        </div>
        <div class="mt-5 flex-1 h-0 overflow-y-auto">
            <nav class="flex-1 py-4 bg-blue-800">
                @foreach($sidebar as $row)
                    <a class="group flex items-center p-4 text-sm leading-5 font-medium text-white bg-blue-800 hover:bg-blue-900 focus:outline-none focus:bg-blue-900 transition ease-in-out duration-150 {{ isActive($row['url']) }}"
                       href="{{ route($row['url']) }}">
                        <img src="{{ asset('images/svg/' . $row['icon'] . '.svg') }}"
                             class="w-5 h-5 fill-current text-white mr-3" alt=""/>
                        {{ $row['title'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </div>
</div>
