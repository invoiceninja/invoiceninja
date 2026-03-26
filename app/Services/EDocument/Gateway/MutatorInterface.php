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

namespace App\Services\EDocument\Gateway;

interface MutatorInterface
{
    public function receiverSpecificLevelMutators(): self;

    public function senderSpecificLevelMutators(): self;

    public function setInvoice($invoice): self;

    /**
     * @param \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $p_invoice
     */
    public function setPeppol($p_invoice): self;

    /**
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote
     */
    public function getPeppol(): mixed;

    public function setClientSettings($client_settings): self;

    public function setCompanySettings($company_settings): self;

    public function getClientSettings(): mixed;

    public function getCompanySettings(): mixed;

    public function getInvoice(): mixed;

    public function getSetting(string $property_path): mixed;

    public function setOverrideVatNumber(string $vat_number): self;

    public function getOverrideVatNumber(): string;
}
