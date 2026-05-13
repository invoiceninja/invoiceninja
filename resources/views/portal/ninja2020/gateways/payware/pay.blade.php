@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'payware', 'card_title' => 'payware'])

@section('gateway_head')
    <style>
        #payware-qr-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem;
        }
        #payware-qr-container img {
            max-width: 250px;
            max-height: 250px;
            width: 100%;
            height: auto;
        }
        .payware-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 0.25rem 0;
        }
        .payware-info-label {
            font-size: 0.875rem;
            color: #6b7280;
        }
        .payware-info-value {
            font-size: 0.875rem;
            font-weight: 500;
            color: #111827;
        }
        .payware-countdown {
            font-size: 1.125rem;
            font-weight: 600;
            color: #dc2626;
        }
        .payware-countdown.active {
            color: #059669;
        }
        .payware-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .payware-status.pending {
            color: #92400e;
            background-color: #fef3c7;
            border-radius: 0.375rem;
        }
        .payware-status.confirmed {
            color: #065f46;
            background-color: #d1fae5;
            border-radius: 0.375rem;
        }
        .payware-status.failed {
            color: #991b1b;
            background-color: #fee2e2;
            border-radius: 0.375rem;
        }
        .payware-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: payware-spin 0.75s linear infinite;
        }
        @@keyframes payware-spin {
            to { transform: rotate(360deg); }
        }
        .payware-payment-id {
            font-family: monospace;
            font-size: 0.75rem;
            word-break: break-all;
            cursor: pointer;
        }
        .payware-copy-btn {
            background: none;
            border: 1px solid #d1d5db;
            border-radius: 0.25rem;
            padding: 0.125rem 0.375rem;
            font-size: 0.75rem;
            cursor: pointer;
            color: #6b7280;
        }
        .payware-copy-btn:hover {
            background-color: #f3f4f6;
        }
        .payware-qr-container { display: flex; }
        .payware-deeplink-container { display: none; }
        @@media (max-width: 640px) {
            .payware-qr-container { display: none !important; }
            .payware-deeplink-container { display: flex !important; }
        }
    </style>
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="token">
    </form>

    @include('portal.ninja2020.gateways.includes.payment_details')

    <div id="payware-payment-container">
        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
            payware
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment') . ' ID'])
            <span class="payware-payment-id" id="payware-payment-id" title="Click to copy">{{ substr($transaction_id, 0, 16) }}...</span>
            <button type="button" class="payware-copy-btn" onclick="paywareCopyId()">{{ ctrans('texts.copy') }}</button>
        @endcomponent

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.expires')])
            <span class="payware-countdown active" id="payware-countdown">--:--</span>
        @endcomponent

        <div class="payware-qr-container" id="payware-qr-container" style="flex-direction: column; align-items: center; padding: 1rem;"></div>

        <div class="payware-deeplink-container" id="payware-deeplink-container" style="flex-direction: column; align-items: center; padding: 1rem;">
            <a href="payware://{{ $transaction_id }}" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; background-color: #059669; color: #ffffff; font-weight: 600; font-size: 1rem; border-radius: 0.5rem; text-decoration: none;">
                {{ ctrans('texts.pay_now') }}
            </a>
        </div>

        <div class="px-4 py-3 sm:px-6">
            <div class="payware-status pending" id="payware-status">
                <span class="payware-spinner"></span>
                <span id="payware-status-text">{{ ctrans('texts.payment_status_1') }}</span>
            </div>
        </div>
    </div>

    <div id="payware-expired-container" style="display: none;">
        @component('portal.ninja2020.components.general.card-element-single')
            <div class="payware-status failed">
                <span>{{ ctrans('texts.payment') }} expired. Please go back and try again.</span>
            </div>
        @endcomponent
    </div>

    <div id="payware-error-container" style="display: none;">
        @component('portal.ninja2020.components.general.card-element-single')
            <div class="payware-status failed">
                <span id="payware-error-text"></span>
            </div>
        @endcomponent
    </div>
@endsection

@push('footer')
    <script>
        (function() {
            const transactionId = @json($transaction_id);

            // Generate QR code (hidden on mobile via CSS)
            var script = document.createElement('script');
            script.src = @json(asset('vendor/qrcodejs/qrcode.min.js'));
            script.onload = function() {
                var qrContainer = document.getElementById('payware-qr-container');
                if (qrContainer) {
                    new QRCode(qrContainer, {
                        text: 'payware://' + transactionId,
                        width: 250,
                        height: 250,
                        colorDark: '#000000',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.Q,
                    });
                }
            };
            document.head.appendChild(script);

            const paymentHash = @json($payment_hash);
            const companyGatewayId = @json($gateway->getCompanyGatewayId());
            const paymentMethodId = @json($payment_method_id);
            const timeToLive = @json($time_to_live);
            const responseUrl = @json(route('client.payments.response'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]')?.value;

            let secondsRemaining = timeToLive;
            let pollInterval = null;
            let countdownInterval = null;

            function updateCountdown() {
                if (secondsRemaining <= 0) {
                    clearInterval(countdownInterval);
                    clearInterval(pollInterval);
                    document.getElementById('payware-payment-container').style.display = 'none';
                    document.getElementById('payware-expired-container').style.display = 'block';
                    return;
                }

                const minutes = Math.floor(secondsRemaining / 60);
                const seconds = secondsRemaining % 60;
                const display = minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
                const el = document.getElementById('payware-countdown');
                el.textContent = display;

                if (secondsRemaining <= 60) {
                    el.classList.remove('active');
                } else {
                    el.classList.add('active');
                }

                secondsRemaining--;
            }

            function checkStatus() {
                const formData = new FormData();
                formData.append('_token', csrfToken);
                formData.append('company_gateway_id', companyGatewayId);
                formData.append('payment_method_id', paymentMethodId);
                formData.append('payment_hash', paymentHash);
                formData.append('payware_check_status', '1');

                fetch(responseUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData,
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.status === 'CONFIRMED') {
                        clearInterval(pollInterval);
                        clearInterval(countdownInterval);

                        const statusEl = document.getElementById('payware-status');
                        statusEl.className = 'payware-status confirmed';
                        statusEl.innerHTML = '<span>' + '{{ ctrans("texts.payment_status_4") }}' + '</span>';

                        if (data.redirect) {
                            setTimeout(function() {
                                window.location.href = data.redirect;
                            }, 1500);
                        }
                    } else if (data.status === 'DECLINED' || data.status === 'FAILED' || data.status === 'CANCELLED' || data.status === 'EXPIRED') {
                        clearInterval(pollInterval);
                        clearInterval(countdownInterval);

                        document.getElementById('payware-payment-container').style.display = 'none';
                        document.getElementById('payware-error-container').style.display = 'block';
                        document.getElementById('payware-error-text').textContent =
                            data.message || 'Payment was not completed. Please try again.';
                    }
                })
                .catch(function() {
                    // Silently ignore polling errors - will retry on next interval
                });
            }

            // Start countdown
            updateCountdown();
            countdownInterval = setInterval(updateCountdown, 1000);

            // Start polling
            pollInterval = setInterval(checkStatus, 3000);

            // Copy payment ID to clipboard
            window.paywareCopyId = function() {
                navigator.clipboard.writeText(transactionId).then(function() {
                    const btn = document.querySelector('.payware-copy-btn');
                    const original = btn.textContent;
                    btn.textContent = '{{ ctrans("texts.link_copied") }}';
                    setTimeout(function() { btn.textContent = original; }, 2000);
                });
            };
        })();
    </script>
@endpush
