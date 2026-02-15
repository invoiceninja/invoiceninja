<div id="payware-payment-wrapper">
    <div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
        id="payware-payment">

        <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
            @csrf
            <input type="hidden" name="gateway_response">
            <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
            <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
            <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
            <input type="hidden" name="token">
        </form>

        @include('portal.ninja2020.gateways.includes.payment_details')

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
            {{ ctrans('texts.mobile_payment') }}
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment') . ' ID'])
            <span style="display: inline-flex; align-items: center; gap: 0.375rem;">
                <span style="font-family: monospace; font-size: 0.75rem;" id="payware-payment-id">{{ $transaction_id }}</span>
                <button type="button" style="background: none; border: none; padding: 0.125rem; cursor: pointer; color: #6b7280; display: inline-flex; align-items: center;" onclick="paywareCopyId()" title="{{ ctrans('texts.copy') }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                </button>
            </span>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.expires')])
            <span style="font-weight: 600; color: #059669;" id="payware-countdown">--:--</span>
        @endcomponent

        <div style="display: flex; flex-direction: column; align-items: center; padding: 1rem;" id="payware-qr-container">
            @if($qr_image_data)
                <img src="data:image/svg+xml;base64,{{ $qr_image_data }}" alt="payware QR Code" style="max-width: 250px; max-height: 250px; width: 100%; height: auto;">
            @endif
        </div>

        <div class="px-4 py-3 sm:px-6" id="payware-status-container">
            <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem; font-size: 0.875rem; font-weight: 500; color: #92400e; background-color: #fef3c7; border-radius: 0.375rem;" id="payware-status">
                <span style="display: inline-block; width: 1rem; height: 1rem; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: payware-spin 0.75s linear infinite;" id="payware-spinner"></span>
                <span id="payware-status-text">{{ ctrans('texts.payware_awaiting_payment') }}</span>
            </div>
        </div>

        <div style="display: flex; align-items: center; justify-content: center; gap: 0.375rem; padding: 0.75rem; border-top: 1px solid #e5e7eb;">
            <span style="font-size: 0.7rem; color: #9ca3af;">{{ ctrans('texts.payware_certified_by') }}</span>
            <img src="{{ asset('gateway-card-images/payware-logo.svg') }}" alt="payware" style="height: 16px;">
        </div>
    </div>

    <style>
        @@keyframes payware-spin {
            to { transform: rotate(360deg); }
        }
    </style>

    @script
    <script>
    const transactionId = @json($transaction_id);
    const paymentHash = @json($payment_hash);
    const timeToLive = @json($time_to_live);
    const statusUrl = @json($gateway->genericWebhookUrl()) + '?check_status=1&payment_hash=' + encodeURIComponent(@json($payment_hash));

    let secondsRemaining = timeToLive;
    let pollInterval = null;
    let countdownInterval = null;

    function updateCountdown() {
        if (secondsRemaining <= 0) {
            clearInterval(countdownInterval);
            clearInterval(pollInterval);
            document.getElementById('payware-qr-container').style.display = 'none';
            const statusEl = document.getElementById('payware-status');
            statusEl.style.color = '#991b1b';
            statusEl.style.backgroundColor = '#fee2e2';
            document.getElementById('payware-spinner').style.display = 'none';
            document.getElementById('payware-status-text').textContent = '{{ ctrans("texts.payware_payment_expired") }}';
            return;
        }

        const minutes = Math.floor(secondsRemaining / 60);
        const seconds = secondsRemaining % 60;
        document.getElementById('payware-countdown').textContent =
            minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');

        if (secondsRemaining <= 60) {
            document.getElementById('payware-countdown').style.color = '#dc2626';
        }

        secondsRemaining--;
    }

    function checkStatus() {
        fetch(statusUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.status === 'CONFIRMED') {
                clearInterval(pollInterval);
                clearInterval(countdownInterval);
                const statusEl = document.getElementById('payware-status');
                statusEl.style.color = '#065f46';
                statusEl.style.backgroundColor = '#d1fae5';
                document.getElementById('payware-spinner').style.display = 'none';
                document.getElementById('payware-status-text').textContent = '{{ ctrans("texts.payware_payment_confirmed") }}';
                if (data.redirect) {
                    setTimeout(function() { window.location.href = data.redirect; }, 1500);
                }
            } else if (data.status === 'DECLINED' || data.status === 'FAILED') {
                clearInterval(pollInterval);
                clearInterval(countdownInterval);
                const statusEl = document.getElementById('payware-status');
                statusEl.style.color = '#991b1b';
                statusEl.style.backgroundColor = '#fee2e2';
                document.getElementById('payware-spinner').style.display = 'none';
                document.getElementById('payware-status-text').textContent =
                    data.message || 'Payment was not completed. Please try again.';
            }
        })
        .catch(function() {});
    }

    updateCountdown();
    countdownInterval = setInterval(updateCountdown, 1000);
    pollInterval = setInterval(checkStatus, 3000);

    window.paywareCopyId = function() {
        navigator.clipboard.writeText(transactionId).then(function() {
            const btn = event.target;
            const original = btn.textContent;
            btn.textContent = '{{ ctrans("texts.copied") }}';
            setTimeout(function() { btn.textContent = original; }, 2000);
        });
    };
</script>
    @endscript
</div>
