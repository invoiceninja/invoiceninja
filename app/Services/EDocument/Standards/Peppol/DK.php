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

class DK extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return ["B+G", "DK:DIGST", "DK:ERST", "DK:DIGST"];
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        $override_vat_number = $mutator_util->mutator->getOverrideVatNumber();

        // if we are overriding set the correct generic scheme vs DK scheme
        $scheme_id = strlen($override_vat_number) > 1 ? '0037' : '0184';

        $companyID = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\CompanyID();
        $companyID->schemeID = $scheme_id;
        $companyID->value = strlen($override_vat_number) > 1 ? $override_vat_number : preg_replace("/[^a-zA-Z0-9]/", "", $invoice->company->settings->id_number);

        $p_invoice->AccountingSupplierParty->Party->PartyLegalEntity[0]->CompanyID = $companyID;

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }
}
