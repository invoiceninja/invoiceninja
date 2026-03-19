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

namespace App\Services\EDocument\Gateway\Storecove;

use App\Services\EDocument\Gateway\MutatorUtil;
use App\Services\EDocument\Gateway\MutatorInterface;
use App\Services\EDocument\Standards\Peppol\CountryFactory;

class Mutator implements MutatorInterface
{
    /** @var \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote */
    private \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $p_invoice;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_client_settings;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_company_settings;

    private $invoice;

    private array $storecove_meta = [];

    private MutatorUtil $mutator_util;

    public function __construct(public Storecove $storecove)
    {
        $this->mutator_util = new MutatorUtil($this);
    }

    /**
     * setInvoice
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
     * setPeppol
     *
     * @param  \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $p_invoice
     * @return self
     */
    public function setPeppol($p_invoice): self
    {
        $this->p_invoice = $p_invoice;
        return $this;
    }

    /**
     * getPeppol
     *
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote
     */
    public function getPeppol(): mixed
    {
        return $this->p_invoice;
    }

    /**
     * setClientSettings
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
     * setCompanySettings
     *
     * @param  \InvoiceNinja\EInvoice\Models\Peppol\Invoice $company_settings
     * @return self
     */
    public function setCompanySettings($company_settings): self
    {
        $this->_company_settings = $company_settings;
        return $this;
    }

    /**
     * getClientSettings
     *
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice
     */
    public function getClientSettings(): mixed
    {
        return $this->_client_settings;
    }

    /**
     * getCompanySettings
     *
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice
     */
    public function getCompanySettings(): mixed
    {
        return $this->_company_settings;
    }

    /**
     * getInvoice
     *
     * @return mixed
     */
    public function getInvoice(): mixed
    {
        return $this->invoice;
    }

    /**
     * getSetting
     *
     * @param  string $property_path
     * @return mixed
     */
    public function getSetting(string $property_path): mixed
    {
        return $this->mutator_util->getSetting($property_path);
    }

    /**
     * senderSpecificLevelMutators
     *
     * Dispatches to the appropriate country handler based on the sender's country.
     *
     * @return self
     */
    public function senderSpecificLevelMutators(): self
    {
        $countryCode = $this->invoice->company->country()->iso_3166_2;

        $handler = CountryFactory::make($countryCode);
        $result = $handler->senderMutations(
            $this->p_invoice,
            $this->invoice,
            $this->mutator_util,
            $this->storecove_meta
        );

        $this->p_invoice = $result['p_invoice'];
        $this->storecove_meta = $result['storecove_meta'];

        return $this;
    }

    /**
     * receiverSpecificLevelMutators
     *
     * Dispatches to the appropriate country handler based on the receiver's country.
     *
     * @return self
     */
    public function receiverSpecificLevelMutators(): self
    {
        $countryCode = $this->invoice->client->country->iso_3166_2;

        $handler = CountryFactory::make($countryCode);
        $result = $handler->receiverMutations(
            $this->p_invoice,
            $this->invoice,
            $this->mutator_util,
            $this->storecove_meta
        );

        $this->p_invoice = $result['p_invoice'];
        $this->storecove_meta = $result['storecove_meta'];

        return $this;
    }

    /////////////// Storecove Helpers ///////////////
    private function getIndividualEmailRoute(): string
    {
        return $this->invoice->client->present()->email();
    }

    private function getClientPublicIdentifier(string $code): string
    {
        if ($this->invoice->client->classification == 'individual' && strlen($this->invoice->client->id_number ?? '') > 2) {
            return preg_replace("/[^a-zA-Z0-9]/", "", $this->invoice->client->id_number ?? '');
        }

        return preg_replace("/[^a-zA-Z0-9]/", "", $this->invoice->client->vat_number ?? '');
    }

    public function setClientRoutingCode(): self
    {

        if ($this->invoice->client->classification == 'individual' || (strlen($this->invoice->client->vat_number ?? '') < 2 && strlen($this->invoice->client->id_number ?? '') < 2)) {
            return $this->setEmailRouting($this->getIndividualEmailRoute());
        }

        //Regardless, always include the client email address as a route - Storecove will only use this as a fallback.
        $client_email = $this->getIndividualEmailRoute();

        if (strlen($client_email) > 2) {
            $this->setEmailRouting($client_email);
        }


        if (stripos($this->invoice->client->routing_id ?? '', ":") !== false) {

            $parts = explode(":", $this->invoice->client->routing_id);

            if (count($parts) == 2) {
                $scheme = $parts[0];
                $id = $parts[1];

                if ($this->storecove->discovery($id, $scheme)) {
                    $this->setStorecoveMeta($this->buildRouting([
                        ["scheme" => $scheme, "id" => $id],
                    ]));

                    return $this;
                }
            }

        }

        $code = $this->getClientRoutingCode();

        $identifier = false;

        if ($this->invoice->client->country->iso_3166_2 == 'FR') {
            $identifier = $this->invoice->client->id_number;
        } else {
            $identifier = $this->invoice->client->vat_number;
        }

        if ($this->invoice->client->country->iso_3166_2 == 'DE' && $this->invoice->client->classification == 'government') {
            $identifier = $this->invoice->client->routing_id;
        }

        if (!$identifier) {
            $identifier = $this->getClientPublicIdentifier($code);
        }

        $identifier = str_ireplace(["FR", "BE"], "", $identifier);
        $identifier = preg_replace("/[^a-zA-Z0-9]/", "", $identifier);


        //Check the recipient is on the network, and can be delivered the correct document.
        if($this->invoice->client->country->iso_3166_2 == "BE"){

            if ($this->storecove->discovery($identifier, 'BE:EN')) {
                    $this->setStorecoveMeta($this->buildRouting([
                        ["scheme" => 'BE:EN', "id" => $identifier],
                    ]));

                    return $this;
            }
            elseif($this->storecove->discovery("BE".$identifier, 'BE:VAT')) {
                $this->setStorecoveMeta($this->buildRouting([
                    ["scheme" => 'BE:VAT', "id" => "BE".$identifier],
                ]));

                return $this;
            }

        }


        $this->setStorecoveMeta($this->buildRouting([
            ["scheme" => $code, "id" => $identifier],
        ]));


        return $this;
    }

    /**
     * getClientRoutingCode
     *
     * @return string
     */
    private function getClientRoutingCode(): string
    {
        return (new StorecoveRouter())->setInvoice($this->invoice)->resolveRouting($this->invoice->client->country->iso_3166_2, $this->invoice->client->classification);
    }


    /**
     * Builds the Routing object for StoreCove
     *
     * @param  array $identifiers
     * @return array
     */
    private function buildRouting(array $identifiers): array
    {
        return
        [
            "routing" => [
                "eIdentifiers"
                    => $identifiers,

            ],
        ];
    }


    /**
     * setEmailRouting
     *
     * @param  string $email
     * @return self
     */
    private function setEmailRouting(string $email): self
    {
        $meta = $this->getStorecoveMeta();

        if (isset($meta['routing']['emails'])) {
            $emails = $meta['routing']['emails'];
            array_push($emails, $email);
            $meta['routing']['emails'] = $emails;
        } else {
            $meta['routing']['emails'] = [$email];
        }

        $this->setStorecoveMeta($meta);

        return $this;
    }



    /**
     * setStorecoveMeta
     *
     * updates the storecove payload for sending documents
     *
     * @param  array $meta
     * @return self
     */
    private function setStorecoveMeta(array $meta): self
    {

        $this->storecove_meta = array_merge_recursive($this->storecove_meta, $meta);

        return $this;
    }

    /**
     * getStorecoveMeta
     *
     * @return array
     */
    public function getStorecoveMeta(): array
    {
        return $this->storecove_meta;
    }


}
