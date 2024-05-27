<div id="invoiceninja"></div>

<script src="{{ asset('vendor/invoiceninja.umd.js') }}"></script>

<script type="module">
    invoiceninja({
        id: '{{ $subscription->hashed_id }}',
        url: '{{ config("app.url") }}',
        container: document.getElementById('invoiceninja'),
    });
</script>
