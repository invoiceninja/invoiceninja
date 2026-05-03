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

namespace App\Services\EDocument\Gateway\Qvalia;

use App\Services\EDocument\Gateway\MutatorUtil;
use App\Services\EDocument\Gateway\MutatorInterface;

class Mutator implements MutatorInterface
{
    /** @var \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote */
    private \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $p_invoice;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_client_settings;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_company_settings;

    private $invoice;

    private string $override_vat_number = '';

    private MutatorUtil $mutator_util;

    public function __construct(public Qvalia $qvalia)
    {
        $this->mutator_util = new MutatorUtil($this);
    }

    /**
     * Sets the Invoice Ninja invoice/credit model being processed.
     *
     * @param  mixed $invoice
     * @return self
     */
    public function setInvoice($invoice): self
    {
        $this->invoice = $invoice;
        return $this;
    }

    /**
     * Sets the Peppol invoice or credit note model.
     *
     * @param \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $p_invoice
     * @return self
     */
    public function setPeppol($p_invoice): self
    {
        $this->p_invoice = $p_invoice;
        return $this;
    }

    /**
     * Returns the current Peppol invoice or credit note model.
     *
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote
     */
    public function getPeppol(): mixed
    {
        return $this->p_invoice;
    }

    /**
     * Returns the client-level e-invoice settings.
     *
     * @return mixed
     */
    public function getClientSettings(): mixed
    {
        return $this->_client_settings;
    }

    /**
     * Returns the company-level e-invoice settings.
     *
     * @return mixed
     */
    public function getCompanySettings(): mixed
    {
        return $this->_company_settings;
    }

    /**
     * Sets the client-level e-invoice settings.
     *
     * @param  mixed $client_settings
     * @return self
     */
    public function setClientSettings($client_settings): self
    {
        $this->_client_settings = $client_settings;
        return $this;
    }

    /**
     * Sets the company-level e-invoice settings.
     *
     * @param  mixed $company_settings
     * @return self
     */
    public function setCompanySettings($company_settings): self
    {
        $this->_company_settings = $company_settings;
        return $this;
    }

    /**
     * Returns the Invoice Ninja invoice/credit model.
     *
     * @return mixed
     */
    public function getInvoice(): mixed
    {
        return $this->invoice;
    }

    /**
     * Resolves a setting value by property path via MutatorUtil.
     *
     * @param  string $property_path
     * @return mixed
     */
    public function getSetting(string $property_path): mixed
    {
        return $this->mutator_util->getSetting($property_path);
    }

    /**
     * Sets an override VAT number for the supplier party.
     *
     * @param  string $vat_number
     * @return self
     */
    public function setOverrideVatNumber(string $vat_number): self
    {
        $this->override_vat_number = $vat_number;
        return $this;
    }

    /**
     * Returns the override VAT number, if set.
     *
     * @return string
     */
    public function getOverrideVatNumber(): string
    {
        return $this->override_vat_number;
    }

    /**
     * senderSpecificLevelMutators
     *
     * Runs sender level specific requirements for the e-invoice,
     *
     * ie, mutations that are required by the senders country.
     *
     * @return self
     */
    public function senderSpecificLevelMutators(): self
    {

        if (method_exists($this, $this->invoice->company->country()->iso_3166_2)) {
            $this->{$this->invoice->company->country()->iso_3166_2}();
        }

        return $this;
    }

    /**
     * receiverSpecificLevelMutators
     *
     * Runs receiver level specific requirements for the e-invoice
     *
     * ie mutations that are required by the receiving country
     * @return self
     */
    public function receiverSpecificLevelMutators(): self
    {

        if (method_exists($this, "client_{$this->invoice->company->country()->iso_3166_2}")) {
            $this->{"client_{$this->invoice->company->country()->iso_3166_2}"}();
        }

        return $this;
    }

    // Country-specific methods
    public function DE(): self
    {
        return $this;
    }
    public function CH(): self
    {
        return $this;
    }
    public function AT(): self
    {
        return $this;
    }
    public function AU(): self
    {
        return $this;
    }
    public function ES(): self
    {
        return $this;
    }
    public function FI(): self
    {
        return $this;
    }
    public function FR(): self
    {
        return $this;
    }
    public function IT(): self
    {
        return $this;
    }
    public function client_IT(): self
    {
        return $this;
    }
    public function MY(): self
    {
        return $this;
    }
    public function NL(): self
    {
        return $this;
    }
    public function NZ(): self
    {
        return $this;
    }
    public function PL(): self
    {
        return $this;
    }
    public function RO(): self
    {
        return $this;
    }
    public function SG(): self
    {
        return $this;
    }
    public function SE(): self
    {
        return $this;
    }

}
