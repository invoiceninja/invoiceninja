<div class="btc-value-wrapper">
    <div class="btc-value">
        1 BTC = {{ $btc_price }} {{ $currency }}, updates in <span id="countdown-livewire">{{ $countdown }}</span>
    </div>
    <span class="icon-refresh {{ $is_refreshing ? 'rotating' : '' }}" wire:click="refreshBTCPrice"
          {{ $is_refreshing ? 'style="pointer-events: none;"' : '' }}></span>

    <style>
        .btc-value-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: row;
        }

        .btc-value {
            font-size: 14px;
            text-align: center;
        }

        .icon-refresh {
            cursor: pointer;
            margin-left: 5px;
            width: 28px;
            display: flex;
            font-size: 32px;
            margin-bottom: 5px;
        }


        @keyframes rotating {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        .rotating {
            animation: rotating 2s linear infinite;
        }
    </style>

    @script
        <script>
            let countdownInterval = null;
            let countdownDate = null;

            const updateCountdown = () => {
                if (!countdownDate) return;

                const now = new Date().getTime();
                const distance = countdownDate - now;

                if (distance <= 0) {
                    clearInterval(countdownInterval);
                    countdownInterval = null;
                    $wire.refreshBTCPrice();
                    return;
                }

                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                if (!isNaN(minutes) && !isNaN(seconds)) {
                    const formattedMinutes = String(minutes).padStart(2, '0');
                    const formattedSeconds = String(seconds).padStart(2, '0');

                    const countdownElement = document.getElementById('countdown-livewire');
                    if (countdownElement) {
                        countdownElement.textContent = formattedMinutes + ':' + formattedSeconds;
                    }
                }
            };

            const startCountdownTimer = ({ duration }) => {
                clearInterval(countdownInterval);
                countdownInterval = null;
                countdownDate = new Date().getTime() + duration * 1000;
                updateCountdown();
                countdownInterval = setInterval(updateCountdown, 1000);
            };

            $wire.on('start-countdown', startCountdownTimer);

            // Listen for updates after price refresh
            $wire.on('btc-price-updated', () => {
                startCountdownTimer({ duration: 600 });
            });

            // Initialize countdown on component mount with a small delay to ensure DOM is ready
            setTimeout(() => {
                startCountdownTimer({ duration: 600 });
            }, 100);
        </script>
    @endscript
</div>
