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

namespace App\Helpers\Epc;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Utils\Ninja;
use BaconQrCode\Exception\InvalidArgumentException;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * EpcQrGenerator.
 */
class EpcQrGenerator
{
    // private array $sepa = [
    //     'serviceTag' => 'BCD',
    //     'version' => 2,
    //     'characterSet' => 1,
    //     'identification' => 'SCT',
    //     'bic' => '',
    //     'purpose' => '',

    // ];

    public function __construct(protected Company $company, protected Invoice|RecurringInvoice $invoice, protected float $amount)
    {
    }

    public function getQrCode()
    {
        $qr = '';

        try {
            $renderer = new ImageRenderer(
                new RendererStyle(200),
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);

            $qr = $writer->writeString($this->encodeMessage(), 'utf-8');

            return htmlspecialchars("<svg viewBox='0 0 200 200' width='200' height='200' x='0' y='0' xmlns='http://www.w3.org/2000/svg'>
          <rect x='0' y='0' width='100%'' height='100%' />{$qr}</svg>");

        } catch (\Throwable $e) {
            nlog("EPC QR failure => ".$e->getMessage());
            return '';
        }

    }

    public function encodeMessage()
    {

        $name = $this->company->present()->name();

        if (isset($this->company->e_invoice->Invoice->PaymentMeans) && ($pm = $this->company->e_invoice->Invoice->PaymentMeans[0] ?? false) && in_array($pm->PaymentMeansCode->value, ['30', '58'])) {

            $iban = $pm->PayeeFinancialAccount->ID->value;
            $bic = $pm->PayeeFinancialAccount->FinancialInstitutionBranch->FinancialInstitution->ID->value ?? '';
            
            if(isset($pm->PayeeFinancialAccount->Name) && strlen($pm->PayeeFinancialAccount->Name ?? '') > 0) {
                $name = $pm->PayeeFinancialAccount->Name;
            }

        } else {

            $bic = isset($this->company?->custom_fields?->company2) ? $this->company->settings->custom_value2 : '';
            $iban = isset($this->company?->custom_fields?->company1) ? $this->company->settings->custom_value1 : '';

        }

        $data = [
            'BCD',
            '002', // Version
            '1', // Encoding: 1 = UTF-8
            'SCT', // Service Tag: SEPA Credit Transfer
            $bic, // BIC
            $name, // Recipient Name - Account Name
            $iban, // IBAN
            $this->formatMoney($this->amount), // Amount with EUR prefix
            '', // Reference
            substr(($this->invoice->number ?? ''), 0, 34) // Unstructured remittance information
        ];

        return implode("\n", $data);

    }

    private function formatMoney($value)
    {
        return sprintf('EUR%s', number_format($value, 2, '.', ''));
    }
}
