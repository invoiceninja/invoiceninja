<div class="qrcode-wrapper">
    @if ($error_message)
        <div class="error-message">{{ $error_message }}</div>
    @elseif ($is_loading)
        <div class="loading-message">Loading QR code...</div>
    @else
        <a href="bitcoin:{{ $btc_address }}?amount={{ $btc_amount }}" id="qr-code-link" target="_blank">
            <div id="qrcode-container">
                {!! $qr_code_svg !!}
            </div>
        </a>
    @endif

    <style>
        .qrcode-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .error-message {
            color: #d32f2f;
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }

        .loading-message {
            color: #666;
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }

        #qrcode-container {
            display: flex;
            justify-content: center;
            align-items: center;
        }
    </style>
</div>
