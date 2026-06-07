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

        <div style="position: relative;">
            @include('portal.ninja2020.gateways.includes.payment_details')
            <img src="{{ asset('gateway-card-images/payware-certified-rc.svg') }}" alt="payware certified" style="position: absolute; top: 1.25rem; right: 1.5rem; height: 28px;">
        </div>

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
            {{ ctrans('texts.mobile_payment') }}
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment') . ' ID'])
            <span style="display: inline-flex; align-items: center; gap: 0.375rem;">
                <span class="text-sm leading-5 text-gray-900" id="payware-payment-id">{{ $transaction_id }}</span>
                <button type="button" style="background: none; border: none; padding: 0.125rem; cursor: pointer; color: #6b7280; display: inline-flex; align-items: center;" onclick="paywareCopyId(event)" title="{{ ctrans('texts.copy') }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                </button>
            </span>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.expires')])
            <span style="font-weight: 600; color: #059669;" id="payware-countdown">--:--</span>
        @endcomponent

        <div class="payware-qr-container" style="flex-direction: column; align-items: center; padding: 1rem;" id="payware-qr-container"></div>
        <div id="payware-qr-fallback" style="display: none; padding: 1rem; text-align: center; font-size: 0.875rem; color: #6b7280; word-break: break-all;"></div>

        <div class="payware-deeplink-container" style="flex-direction: column; align-items: center; padding: 1rem;" id="payware-deeplink-container">
            <a href="payware://{{ $transaction_id }}" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; background-color: #059669; color: #ffffff; font-weight: 600; font-size: 1rem; border-radius: 0.5rem; text-decoration: none;">
                {{ ctrans('texts.pay_now') }}
            </a>
            <span style="margin-top: 0.5rem; font-size: 0.75rem; color: #6b7280; text-align: center;">
                {{ ctrans('texts.no_compatible_app_installed') }}
            </span>
        </div>

        <div class="px-4 py-3 sm:px-6" id="payware-status-container">
            <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem; font-size: 0.875rem; font-weight: 500; color: #92400e; background-color: #fef3c7; border-radius: 0.375rem;" id="payware-status">
                <span style="display: inline-block; width: 1rem; height: 1rem; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: payware-spin 0.75s linear infinite;" id="payware-spinner"></span>
                <span id="payware-status-text">{{ ctrans('texts.awaiting_payment') }}</span>
            </div>
        </div>

    </div>

    <style>
        @@keyframes payware-spin {
            to { transform: rotate(360deg); }
        }
        .payware-qr-container { display: flex; }
        .payware-deeplink-container { display: none; }
        @@media (max-width: 640px) {
            .payware-qr-container { display: none !important; }
            .payware-deeplink-container { display: flex !important; }
        }
    </style>

    @script
    <script>
    const transactionId = @json($transaction_id);
    const paymentHash = @json($payment_hash);
    const timeToLive = @json($time_to_live);
    const statusUrl = @json($gateway->genericWebhookUrl()) + '?check_status=1&payment_hash=' + encodeURIComponent(@json($payment_hash));
    const qrLibUrl = @json(asset('vendor/qrcodejs/qrcode.min.js'));
    const T = {
        confirmed: @json(ctrans('texts.payment_confirmed')),
        expired: @json(ctrans('texts.payment_expired')),
        notCompleted: @json(ctrans('texts.payment_was_not_completed')),
        copied: @json(ctrans("texts.link_copied")),
    };
    const FAILURE_STATUSES = ['DECLINED', 'FAILED', 'CANCELLED', 'EXPIRED'];
    const POLL_INTERVAL_MS = 3000;

    const expiresAt = Date.now() + timeToLive * 1000;
    let pollTimer = null;
    let countdownTimer = null;
    let pollAbort = null;
    let stopped = false;

    function $(id) { return document.getElementById(id); }

    function getRemaining() {
        return Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
    }

    function showQrFallback() {
        const qr = $('payware-qr-container');
        if (qr) qr.style.display = 'none';
        const fallback = $('payware-qr-fallback');
        if (fallback) {
            fallback.style.display = 'block';
            fallback.textContent = 'payware://' + transactionId;
        }
    }

    function loadQrLibrary() {
        const script = document.createElement('script');
        script.src = qrLibUrl;
        script.onload = function () {
            const qrContainer = $('payware-qr-container');
            if (!qrContainer || typeof QRCode === 'undefined') {
                showQrFallback();
                return;
            }
            try {
                new QRCode(qrContainer, {
                    text: 'payware://' + transactionId,
                    width: 250,
                    height: 250,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.Q,
                });
            } catch (_) {
                showQrFallback();
            }
        };
        script.onerror = showQrFallback;
        document.head.appendChild(script);
    }

    function renderExpiredUi() {
        const qr = $('payware-qr-container');
        const dl = $('payware-deeplink-container');
        if (qr) qr.style.display = 'none';
        if (dl) dl.style.display = 'none';
        const statusEl = $('payware-status');
        if (statusEl) {
            statusEl.style.color = '#991b1b';
            statusEl.style.backgroundColor = '#fee2e2';
        }
        const spinner = $('payware-spinner');
        if (spinner) spinner.style.display = 'none';
        const text = $('payware-status-text');
        if (text) text.textContent = T.expired;
    }

    function renderFailureUi(message) {
        const qr = $('payware-qr-container');
        const dl = $('payware-deeplink-container');
        if (qr) qr.style.display = 'none';
        if (dl) dl.style.display = 'none';
        const statusEl = $('payware-status');
        if (statusEl) {
            statusEl.style.color = '#991b1b';
            statusEl.style.backgroundColor = '#fee2e2';
        }
        const spinner = $('payware-spinner');
        if (spinner) spinner.style.display = 'none';
        const text = $('payware-status-text');
        if (text) text.textContent = message || T.notCompleted;
    }

    function renderConfirmedUi() {
        const statusEl = $('payware-status');
        if (statusEl) {
            statusEl.style.color = '#065f46';
            statusEl.style.backgroundColor = '#d1fae5';
        }
        const spinner = $('payware-spinner');
        if (spinner) spinner.style.display = 'none';
        const text = $('payware-status-text');
        if (text) text.textContent = T.confirmed;
    }

    function stopAll() {
        stopped = true;
        if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
        if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
        if (pollAbort) { try { pollAbort.abort(); } catch (_) {} pollAbort = null; }
    }

    function updateCountdown() {
        const remaining = getRemaining();
        if (remaining <= 0) {
            stopAll();
            renderExpiredUi();
            return;
        }
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;
        const el = $('payware-countdown');
        if (el) {
            el.textContent =
                minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
            if (remaining <= 60) el.style.color = '#dc2626';
        }
    }

    function pollOnce() {
        if (stopped) return;
        if (getRemaining() <= 0) return;

        pollAbort = new AbortController();
        fetch(statusUrl, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            signal: pollAbort.signal,
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (stopped) return;
                if (data.status === 'CONFIRMED') {
                    stopAll();
                    renderConfirmedUi();
                    setTimeout(function () { $('server-response').submit(); }, 1500);
                    return;
                }
                if (FAILURE_STATUSES.indexOf(data.status) !== -1) {
                    stopAll();
                    renderFailureUi(data.message);
                    return;
                }
                pollTimer = setTimeout(pollOnce, POLL_INTERVAL_MS);
            })
            .catch(function (err) {
                if (stopped || (err && err.name === 'AbortError')) return;
                pollTimer = setTimeout(pollOnce, POLL_INTERVAL_MS);
            });
    }

    loadQrLibrary();
    updateCountdown();
    countdownTimer = setInterval(updateCountdown, 1000);
    pollTimer = setTimeout(pollOnce, POLL_INTERVAL_MS);

    window.addEventListener('beforeunload', stopAll);

    function legacyCopy(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (_) { ok = false; }
        document.body.removeChild(ta);
        return ok;
    }

    window.paywareCopyId = function (e) {
        const btn = e && e.currentTarget ? e.currentTarget : null;
        const showFeedback = function () {
            if (!btn) return;
            const original = btn.textContent;
            btn.textContent = T.copied;
            setTimeout(function () { btn.textContent = original; }, 2000);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(transactionId).then(showFeedback).catch(function () {
                if (legacyCopy(transactionId)) showFeedback();
            });
        } else if (legacyCopy(transactionId)) {
            showFeedback();
        }
    };
    </script>
    @endscript
</div>
