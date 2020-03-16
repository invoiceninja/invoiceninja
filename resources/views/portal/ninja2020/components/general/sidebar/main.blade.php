<div
    class="h-screen flex overflow-hidden bg-gray-100"
    x-data="{ sidebarOpen: false }"
    @keydown.window.escape="sidebarOpen = false">

    <!-- Off-canvas menu for mobile -->
@include('portal.ninja2020.components.general.sidebar.mobile')

<!-- Static sidebar for desktop -->
    @include('portal.ninja2020.components.general.sidebar.desktop')

    <div class="flex flex-col w-0 flex-1 overflow-hidden">
        @include('portal.ninja2020.components.general.sidebar.header')
        <main
            class="flex-1 relative z-0 overflow-y-auto py-6 focus:outline-none"
            tabindex="0" x-data
            x-init="$el.focus()">

            <div class="mx-auto px-4 sm:px-6 md:px-8">
                <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
                @yield('header')
            </div>

            <div class="mx-auto px-4 sm:px-6 md:px-8">
                <div class="py-4">
                    @includeWhen(session()->has('success'), 'portal.ninja2020.components.general.messages.success')
                    {{ $slot }}
                </div>
            </div>
        </main>
    </div>
</div>
