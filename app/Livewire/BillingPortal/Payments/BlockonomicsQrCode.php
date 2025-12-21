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

use BaconQrCode\Writer;
use Livewire\Component;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;

class BlockonomicsQrCode extends Component
{
    public $btc_address;

    public $btc_amount;

    public $qr_code_svg = '';

    public $is_loading = false;

    public $error_message = '';

    public function mount($btc_address, $btc_amount)
    {
        $this->btc_address = $btc_address;
        $this->btc_amount = $btc_amount;
        $this->fetchQRCode();
    }

    public function fetchQRCode($newBtcAmount = null)
    {
        $this->is_loading = true;
        $this->error_message = '';

        try {
            $btcAmount = $newBtcAmount ?? $this->btc_amount;
            $qrString = "bitcoin:{$this->btc_address}?amount={$btcAmount}";

            $this->qr_code_svg = $this->getPaymentQrCodeRaw($qrString);
        } catch (\Exception $e) {
            $this->error_message = 'Error generating QR code';
        } finally {
            $this->is_loading = false;
        }
    }


    private function getPaymentQrCodeRaw($qr_string)
    {

        $renderer = new ImageRenderer(
            new RendererStyle(150, margin: 0),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);

        $qr = $writer->writeString($qr_string, 'utf-8');

        return $qr;

    }

    public function updateQRCode($btcAmount)
    {
        $this->btc_amount = $btcAmount;
        $this->fetchQRCode($btcAmount);
    }

    public function render()
    {
        return render('components.livewire.blockonomics-qr-code');
    }
}
