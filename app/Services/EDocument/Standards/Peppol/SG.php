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
    ): mixed {

        $company = $invoice->company;
        $uen = $company->settings->id_number ?? '';

        // Fix supplier EndpointID: must be UEN, not GST number
        if (strlen($uen) > 1 && isset($p_invoice->AccountingSupplierParty->Party->EndpointID)) { //@phpstan-ignore-line
            $p_invoice->AccountingSupplierParty->Party->EndpointID->value = preg_replace("/[^a-zA-Z0-9]/", "", $uen);
            $p_invoice->AccountingSupplierParty->Party->EndpointID->schemeID = '0195';
        }

        return $p_invoice;
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
    ): mixed {

        $client = $invoice->client;
        $uen = $client->id_number ?? '';

        $sanitised_uen = preg_replace("/[^a-zA-Z0-9]/", "", $uen);

        // Fix customer EndpointID: must be UEN, not GST number
        if (strlen($uen) > 1 && isset($p_invoice->AccountingCustomerParty->Party->EndpointID)) { //@phpstan-ignore-line
            $p_invoice->AccountingCustomerParty->Party->EndpointID->value = $sanitised_uen;
            $p_invoice->AccountingCustomerParty->Party->EndpointID->schemeID = '0195';
        }

        return $p_invoice;
    }

    /**
     * SG uses CorpPass OAuth + optional C5 IRAS email activation
     * instead of standard identifier registration.
     */
    public function getRegistrationFlow(object $storecove, int $legal_entity_id, array $data): array|\Illuminate\Http\Client\Response|null
    {
        $response = $storecove->startCorpPassFlow($legal_entity_id, $data['id_number']);

        if (!is_array($response)) {
            $storecove->deleteIdentifier($legal_entity_id);
            return $response;
        }

        // Fire C5 IRAS email activation automatically if signer details provided
        if (isset($data['c5_signer_name']) && isset($data['c5_signer_email'])) {
            $c5_response = $storecove->c5->activate(
                $legal_entity_id,
                $data['id_number'],
                $data['c5_signer_name'],
                $data['c5_signer_email'],
            );

        }

        return array_merge($response, [
            'legal_entity_id' => $legal_entity_id,
            'tax_data' => [
                'acts_as_sender' => $data['acts_as_sender'],
                'acts_as_receiver' => $data['acts_as_receiver'],
            ],
        ]);
    }
}
