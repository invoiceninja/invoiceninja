<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Standards\Peppol;

use App\Services\EDocument\Gateway\MutatorUtil;

/**
 * Singapore - CorpPass / InvoiceNow
 *
 * Uses SG:UEN with CorpPass OAuth flow for registration.
 * The UEN (Unique Entity Number) is stored in id_number,
 * while the GST registration number lives in vat_number.
 */
class SG extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return [
            ["G", "SG:UEN", false, "0195:SGUENT08GA0028A"],
            ["B", "SG:UEN", "SG:GST", "SG:UEN"],
        ];
    }

    /**
     * SG sender mutations.
     *
     * Ensures the supplier EndpointID uses the UEN (id_number)
     * rather than the GST number (vat_number), since Singapore
     * Peppol registration is keyed on UEN (scheme 0195).
     */
    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        $company = $invoice->company;
        $uen = $company->settings->id_number ?? '';

        // Fix supplier EndpointID: must be UEN, not GST number
        if (strlen($uen) > 1 && isset($p_invoice->AccountingSupplierParty->Party->EndpointID)) { //@phpstan-ignore-line
            $p_invoice->AccountingSupplierParty->Party->EndpointID->value = preg_replace("/[^a-zA-Z0-9]/", "", $uen);
            $p_invoice->AccountingSupplierParty->Party->EndpointID->schemeID = '0195';
        }

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    /**
     * SG receiver mutations.
     *
     * Ensures the customer EndpointID uses the UEN (id_number)
     * rather than the GST number (vat_number).
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        $client = $invoice->client;
        $uen = $client->id_number ?? '';

        $sanitised_uen = preg_replace("/[^a-zA-Z0-9]/", "", $uen);

        // Fix customer EndpointID: must be UEN, not GST number
        if (strlen($uen) > 1 && isset($p_invoice->AccountingCustomerParty->Party->EndpointID)) { //@phpstan-ignore-line
            $p_invoice->AccountingCustomerParty->Party->EndpointID->value = $sanitised_uen;
            $p_invoice->AccountingCustomerParty->Party->EndpointID->schemeID = '0195';
        }

        // B2G: Storecove requires SG:UEN legal identifier alongside the centralized endpoint
        if ($client->classification === 'government' && strlen($uen) > 1) {
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'SG:UEN', "id" => $sanitised_uen],
            ]));
        }

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }
}
