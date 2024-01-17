<div class="relative z-10 flex-shrink-0 flex h-16 bg-white shadow" xmlns:x-transition="http://www.w3.org/1999/xhtml">
    <button @click.stop="sidebarOpen = true" class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:bg-gray-100 focus:text-gray-600 md:hidden">
        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
        </svg>
    </button>
    <div class="flex-1 px-3 md:px-8 flex justify-between items-center">
        <span class="text-xl text-gray-900" data-ref="meta-title">@yield('meta_title')</span>
        <div class="flex items-center md:ml-6 md:mr-2">
            <div @click.outside="open = false" class="ml-3 relative" x-data="{ open: false }">
                <div>
                    <button data-ref="client-profile-dropdown" @click="open = !open"
                            class="max-w-xs flex items-center text-sm rounded-full focus:outline-none focus:ring">
                        <img class="h-8 w-8 rounded-full" src="{{ auth()->guard('vendor')->user()->avatar() }}" alt=""/>
                        <span class="ml-2 hidden sm:block">{{ auth()->guard('vendor')->user()->present()->name() }}</span>
                    </button>
                </div>
                <div x-show="open" style="display:none;" x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg">
                    <div class="py-1 rounded-md bg-white ring-1 ring-black ring-opacity-5">
                        <a data-ref="client-profile-dropdown-settings"
                           href="{{ route('vendor.profile.edit', ['vendor_contact' => auth()->guard('vendor')->user()->hashed_id]) }}"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150">
                            {{ ctrans('texts.profile') }}
                        </a>

                        <a href="{{ route('vendor.logout') }}"
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition ease-in-out duration-150">
                            {{ ctrans('texts.logout') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
