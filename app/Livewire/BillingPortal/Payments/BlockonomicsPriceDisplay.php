<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire\BillingPortal\Payments;

use Livewire\Component;
use Illuminate\Support\Facades\Http;

class BlockonomicsPriceDisplay extends Component
{
    public $currency;

    public $btc_price;

    public $btc_amount;

    public $countdown = '10:00';

    public $is_refreshing = false;

    protected $listeners = ['refresh-btc-price' => 'refreshBTCPrice'];

    public function mount($currency, $btc_price, $btc_amount)
    {
        $this->currency = $currency;
        $this->btc_price = $btc_price;
        $this->btc_amount = $btc_amount;
        // Countdown will be initialized in the JavaScript @script section
    }

    public function refreshBTCPrice()
    {
        $this->is_refreshing = true;

        nlog('Refreshing BTC price');
        try {
            $response = Http::get('https://www.blockonomics.co/api/price', [
                'currency' => $this->currency,
            ]);

            if ($response->successful()) {
                $price = $response->object()->price ?? null;

                if ($price) {
                    $this->btc_price = $price;
                    $this->btc_amount = number_format($this->btc_amount / $price, 10);

                    // Reset the countdown
                    $this->startCountdown();
                    $this->dispatch('btc-price-updated', [
                        'price' => $price,
                        'amount' => $this->btc_amount,
                    ]);
                }
            }

        } catch (\Exception $e) {
        } finally {
            $this->is_refreshing = false;
        }
    }

    public function startCountdown()
    {
        $this->countdown = '10:00';
        $this->dispatch('start-countdown', ['duration' => 600]);
    }

    public function render()
    {
        return render('components.livewire.blockonomics-price-display');
    }
}
