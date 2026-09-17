<div class="flex min-h-[160px] items-center justify-center gap-3 rounded-lg border bg-white px-4 py-5 shadow-sm" id="gocardless-instant-bank-payment">
    <svg class="h-6 w-6 animate-spin text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 4 5.373 4 12z"></path>
    </svg>
    <span>{{ ctrans('texts.processing') }}</span>

    @script
        <script>
            window.location.assign(@js($authorisation_url));
        </script>
    @endscript
</div>
