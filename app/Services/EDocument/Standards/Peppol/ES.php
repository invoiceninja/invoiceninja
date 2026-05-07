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

class ES extends BaseCountry
{
    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {

        if (!isset($invoice->due_date)) {
            $p_invoice->DueDate = new \DateTime($invoice->date);
        }

        if ($invoice->client->classification == 'business' && $invoice->company->getSetting('classification') == 'business') {
            // B2B requires payment means as credit_transfer
            $mutator_util->setPaymentMeans(true);
        }

        return $p_invoice;
    }
}
