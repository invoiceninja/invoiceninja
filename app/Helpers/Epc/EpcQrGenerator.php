<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Epc;

use App\Models\Company;
use App\Models\Invoice;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * EpcQrGenerator.
 */
class EpcQrGenerator 
{

    private array $sepa = [
        'serviceTag' => 'BCD',
        'version' => 2,
        'characterSet' => 1,
        'identification' => 'SCT',
        'bic' => '',
        'purpose' => '',

    ];

    public function __construct(protected Company $company, protected Invoice $invoice, protected float $amount){}

    public function getQrCode()
    {

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);

        $qr = $writer->writeString($this->encodeMessage());

        return "<svg viewBox='0 0 200 200' width='200' height='200' x='0' y='0' xmlns='http://www.w3.org/2000/svg'>
          <rect x='0' y='0' width='100%'' height='100%' />{$qr}</svg>";

    }

    public function encodeMessage()
    {

        return rtrim(implode("\n", array(
            $this->sepa['serviceTag'],
            sprintf('%03d', $this->sepa['version']),
            $this->sepa['characterSet'],
            $this->sepa['identification'],
            $this->sepa['bic'],
            $this->company->present()->name(),
            $this->company?->custom_fields?->company1 ?: '',
            $this->formatMoney($this->amount),
            $this->sepa['purpose'],
            substr($this->invoice->number,0,34),
            substr($this->invoice->public_notes,0,139),
            ''
        )), "\n");

    }

    private function formatMoney($value) {
        return sprintf('EUR%s', number_format($value, 2, '.', ''));
    }
}