<div
    class="h-screen flex overflow-hidden bg-gray-100"
    x-data="{ sidebarOpen: false }"
    @keydown.window.escape="sidebarOpen = false"
    id="main-sidebar">

    <!-- Off-canvas menu for mobile -->
    @include('portal.ninja2020.components.general.sidebar.mobile')

    <!-- Static sidebar for desktop -->
    @unless(request()->query('sidebar') === 'hidden')
        @include('portal.ninja2020.components.general.sidebar.desktop')
    @endunless

    <div class="flex flex-col w-0 flex-1 overflow-hidden">
        @include('portal.ninja2020.components.general.sidebar.header')
        <main
            class="flex-1 relative z-0 overflow-y-auto pt-6 focus:outline-none"
            tabindex="0" x-data
            x-init="$el.focus()">

            <div class="mx-auto px-4 sm:px-6 md:px-8">
                @yield('header')
            </div>

            <div class="mx-auto px-4 sm:px-6 md:px-8">
                <div class="pt-4 py-6">
                    @includeWhen(session()->has('success'), 'portal.ninja2020.components.general.messages.success')
                    {{ $slot }}
                </div>
            </div>
        </main>
        @include('portal.ninja2020.components.general.footer')
    </div>
</div>

<script>

</script>
