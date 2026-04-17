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
    /**
     * Applies receiver-country-specific mutations to the e-invoice.
     *
     * @return self
     */
    public function receiverSpecificLevelMutators(): self;

    /**
     * Applies sender-country-specific mutations to the e-invoice.
     *
     * @return self
     */
    public function senderSpecificLevelMutators(): self;

    /**
     * Sets the Invoice Ninja invoice/credit model being processed.
     *
     * @param  \App\Models\Invoice|\App\Models\Credit $invoice
     * @return self
     */
    public function setInvoice($invoice): self;

    /**
     * Sets the Peppol invoice or credit note model.
     *
     * @param \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $p_invoice
     * @return self
     */
    public function setPeppol($p_invoice): self;

    /**
     * Returns the current Peppol invoice or credit note model.
     *
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote
     */
    public function getPeppol(): mixed;

    /**
     * Sets the client-level e-invoice settings.
     *
     * @param  mixed $client_settings
     * @return self
     */
    public function setClientSettings($client_settings): self;

    /**
     * Sets the company-level e-invoice settings.
     *
     * @param  mixed $company_settings
     * @return self
     */
    public function setCompanySettings($company_settings): self;

    /**
     * Returns the client-level e-invoice settings.
     *
     * @return mixed
     */
    public function getClientSettings(): mixed;

    /**
     * Returns the company-level e-invoice settings.
     *
     * @return mixed
     */
    public function getCompanySettings(): mixed;

    /**
     * Returns the Invoice Ninja invoice/credit model.
     *
     * @return mixed
     */
    public function getInvoice(): mixed;

    /**
     * Resolves a setting value by property path, checking invoice, client, then company settings.
     *
     * @param  string $property_path
     * @return mixed
     */
    public function getSetting(string $property_path): mixed;

    /**
     * Sets an override VAT number for the supplier party.
     *
     * @param  string $vat_number
     * @return self
     */
    public function setOverrideVatNumber(string $vat_number): self;

    /**
     * Returns the override VAT number, if set.
     *
     * @return string
     */
    public function getOverrideVatNumber(): string;
}
