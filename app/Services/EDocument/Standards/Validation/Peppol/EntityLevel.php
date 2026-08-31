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

namespace App\Services\EDocument\Standards\Validation\Peppol;

use App\Services\EDocument\Support\GlnIdentifier;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\RecurringInvoice;
use Illuminate\Support\Facades\App;
use App\Services\EDocument\Standards\Peppol;
use App\Exceptions\PeppolValidationException;
use App\Services\EDocument\Standards\Validation\EntityLevelInterface;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;
use App\Services\EDocument\Gateway\Storecove\Identifiers\StorecoveIdentifierValidator;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;
use App\Services\EDocument\Standards\Peppol\CountryFactory;

class EntityLevel implements EntityLevelInterface
{
    private array $client_fields = [
        'address1',
        'city',
        'state',
        'postal_code',
        'country_id',
    ];

    private array $company_settings_fields = [
        'address1',
        'city',
        // 'state',
        'postal_code',
        'country_id',
    ];

    private array $company_fields = [
        // 'legal_entity_id',
        // 'vat_number IF NOT an individual
    ];

    private array $invoice_fields = [
        // 'number',
    ];

    private array $errors = [];

    public function __construct(private ?StorecoveIdentifierValidator $identifierValidator = null)
    {
    }

    private function init(string $locale): self
    {

        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($locale);

        return $this;

    }

    public function checkClient(Client $client): array
    {
        $this->init($client->locale());

        $this->errors['client'] = $this->testClientState($client);
        $this->errors['passes'] = count($this->errors['client']) == 0;

        return $this->errors;

    }

    public function checkCompany(Company $company): array
    {

        $this->init($company->locale());
        $this->errors['company'] = $this->testCompanyState($company);
        $this->errors['passes'] = count($this->errors['company']) == 0;

        return $this->errors;

    }

    public function checkRecurringInvoice(RecurringInvoice $recurring_invoice): array
    {
        return ['passes' => true];
    }

    public function checkCredit(Credit $credit): array
    {
        $result = $this->checkInvoice($credit);

        $result['credit'] = $result['invoice'] ?? [];
        $result['invoice'] = [];
        $result['client'] = $result['client'] ?? [];
        $result['company'] = $result['company'] ?? [];

        return $result;
    }

    public function checkInvoice(Invoice|Credit $invoice): array
    {
        $this->init($invoice->client->locale());

        $this->errors['invoice'] = [];
        $this->errors['client'] = $this->testClientState($invoice->client);
        $this->errors['company'] = $this->testCompanyState($invoice->client); // uses client level settings which is what we want

        if (count($this->errors['client']) > 0) {

            $this->errors['passes'] = false;
            return $this->errors;

        }

        // Line item prices may never be negative for Peppol (schematron
        // BR-27/BR-28), for invoices and credit notes alike. Reductions must be
        // expressed with a negative quantity or a discount/allowance, so we
        // reject negative prices up front with a clear, actionable message
        // instead of letting them fail deep in the schematron.
        $negative_price_errors = $this->testNegativeLinePrices($invoice);

        if (count($negative_price_errors) > 0) {

            $this->errors['invoice'] = $negative_price_errors;
            $this->errors['passes'] = false;
            return $this->errors;

        }

        $p = new Peppol($invoice);

        $xml = false;

        try {
            $xml = $p->run()->toXml();

            if (count($p->getErrors()) >= 1) {

                foreach ($p->getErrors() as $error) {
                    $this->errors['invoice'][] = $error;
                }
            }

        } catch (PeppolValidationException $e) {
            $this->errors['invoice'][] = $e->getInvalidField();
        } catch (\Throwable $th) {

        }

        if ($xml) {
            // Second pass through the XSLT validator
            $xslt = new XsltDocumentValidator($xml);
            $errors = $xslt->validate()->getErrors();

            if (isset($errors['stylesheet']) && count($errors['stylesheet']) > 0) {
                $this->errors['invoice'] = array_merge($this->errors['invoice'], $errors['stylesheet']);
            }

            if (isset($errors['general']) && count($errors['general']) > 0) {
                $this->errors['invoice'] = array_merge($this->errors['invoice'], $errors['general']);
            }

            if (isset($errors['xsd']) && count($errors['xsd']) > 0) {
                $this->errors['invoice'] = array_merge($this->errors['invoice'], $errors['xsd']);
            }

        }

        $this->checkNexus($invoice->client);

        $this->errors['passes'] = count($this->errors['invoice']) == 0 && count($this->errors['client']) == 0 && count($this->errors['company']) == 0;

        return $this->errors;

    }

    /**
     * A Peppol item net/gross price may never be negative (schematron
     * BR-27/BR-28), for both invoices and credit notes. When a line carries a
     * negative price the document only fails deep in the schematron with an
     * opaque message, so we surface a clear error here instead.
     *
     * Blank rows are skipped: they are dropped from the Peppol document by
     * PeppolLineBuilder::isBlankItem() and never emit a price, so their sign is
     * irrelevant and flagging them would be a false positive.
     *
     * @param  Invoice|Credit $invoice
     * @return array
     */
    private function testNegativeLinePrices(Invoice|Credit $invoice): array
    {
        foreach ((array) $invoice->line_items as $item) {

            if ($this->isBlankLineItem($item)) {
                continue;
            }

            if ((float) ($item->cost ?? 0) < 0) {
                return [ctrans('texts.peppol_negative_line_price')];
            }
        }

        return [];
    }

    /**
     * Mirrors PeppolLineBuilder::isBlankItem() - a row with a near-zero cost and
     * no usable tax name is dropped from the Peppol document, so it never emits
     * a price to validate against.
     *
     * @param  object $item
     * @return bool
     */
    private function isBlankLineItem(object $item): bool
    {
        return abs((float) ($item->cost ?? 0)) < 0.005
            && strlen((string) ($item->tax_name1 ?? '')) <= 1;
    }

    private function testClientState(Client $client): array
    {

        $errors = [];

        foreach ($this->client_fields as $field) {

            if ($field == 'country_id' && $client->country_id >= 1) {
                continue;
            }

            if (in_array($field, ['address1', 'address2', 'city', 'postal_code']) && strlen($client->{$field} ?? '') < 2) {
                // if (in_array($field, ['address1', 'address2', 'city', 'state', 'postal_code']) && strlen($client->{$field} ?? '') < 2) {
                $errors[] = ['field' => $field, 'label' => ctrans("texts.{$field}")];
            }

            if ($this->validString($client->{$field})) {
                continue;
            }

        }

        if (!$client->country) {
            $errors[] = ['field' => 'country_id', 'label' => ctrans("texts.country")];
            return $errors;
        }

        //Primary contact email is present.
        if ($client->present()->email() == 'No Email Set') {
            $errors[] = ['field' => 'email', 'label' => ctrans("texts.email")];
        }

        if ($client->country_id && $client->country) { //@phpstan-ignore-line
            $non_routable = $client->checkDeliveryNetwork();

            if (is_string($non_routable)) {
                $errors[] = ['field' => 'classification', 'label' => $non_routable];
            }
        }

        // Identifier validation — offline (no network I/O).
        // Only runs once all earlier checks pass AND the client's country is on the Peppol network.
        $peppolCountries = config('einvoice.peppol_network', []);
        if (count($errors) === 0
            && is_array($peppolCountries)
            && in_array($client->country->iso_3166_2, $peppolCountries, true)) {

            $errors = array_merge($errors, $this->testClientIdentifiers($client));
        }

        return $errors;

    }

    /**
     * Validates that the client can be routed on the Peppol network.
     *
     * Country handlers implement receiver-side rules (e.g. OR over candidates for BE,
     * combined IT:IVA + IT:CUUO for Italy B2B/B2G). Explicit routing_id values are
     * validated for format first; valid scheme:id fields still delegate to the handler
     * for composite requirements.
     *
     * Offline validation only — no SMP discovery; that is the send-time
     * RoutingResolver's responsibility.
     *
     * @return array<int, array{field: string, label: string}>
     */
    private function testClientIdentifiers(Client $client): array
    {
        $router         = new StorecoveRouter();
        $country        = $client->country->iso_3166_2;
        $classification = $client->classification ?? 'business';

        // FIRST: explicit routing_id override (scheme:id form). If set, it must
        // validate — malformed routing_id fails here. Valid explicit scheme:id ([]).
        // ends identifier checks (same as send-time GLN / explicit routing). Bare
        // routing_id on IT/DE is deferred (null) so composite IT rules still run.
        $routingError = $this->validateExplicitRoutingId($client);
        if ($routingError !== null && $routingError !== []) {
            return [$routingError];
        }

        if ($routingError === []) {
            return [];
        }

        $senderCountry = $client->company?->country()?->iso_3166_2;

        return CountryFactory::make($country)
            ->validateReceiverRoutingIdentifiers($client, $classification, $router, $senderCountry);
    }

    /**
     * Validates an explicit routing_id override on the client.
     *
     * Returns:
     *   - []    → routing_id is set AND valid — caller should return empty (pass).
     *   - array → routing_id is set AND invalid — caller should return this single error.
     *   - null  → no routing_id set — caller should fall through to handler candidates.
     *
     * A scheme-prefixed routing_id (e.g. "0088:1234567890123") short-circuits
     * validation: it is what the send-time RoutingResolver tries first.
     *
     * @return array{field: string, label: string}|array{}|null
     */
    private function validateExplicitRoutingId(Client $client): ?array
    {
        $value = trim($client->routing_id ?? '');

        if ($value === '') {
            return null;
        }

        // scheme:id form — always validated strictly.
        if (strpos($value, ':') !== false) {
            return $this->validateSchemeColonId($value);
        }

        // Bare value. For countries whose handler natively consumes routing_id
        // (IT wraps as IT:CUUO; DE government wraps as DE:LWID), let the
        // handler interpret the raw value — don't guess here.
        if (CountryFactory::make($client->country->iso_3166_2)
            ->consumesBareRoutingId($client->classification ?? 'business')) {
            return null;
        }

        // Bare value on a country that doesn't natively use routing_id. The
        // user is attempting an override. Numeric values look like GLN
        // attempts; give a GLN-specific error so the user knows what to fix.
        if (ctype_digit($value)) {
            return [
                'field' => 'routing_id',
                'label' => 'For GLN (ICD 0088) use routing_id in the form 0088: followed by exactly 13 digits.',
            ];
        }

        return [
            'field' => 'routing_id',
            'label' => "routing_id \"{$value}\" must be in scheme:id format (e.g. 0088:5401205000102 for GLN).",
        ];
    }

    /**
     * Validates a "scheme:id" routing_id value.
     *
     * @return array{field: string, label: string}|array{} error or [] on pass
     */
    private function validateSchemeColonId(string $value): array
    {
        $parts = explode(':', $value, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return [
                'field' => 'routing_id',
                'label' => ctrans('texts.routing_id') . "'{$value}' must be in scheme:id format (e.g. 0088:5401205000102 for GLN).",
            ];
        }

        [$scheme, $id] = $parts;
        $id = trim($id);

        if ($scheme === '0088') {
            return GlnIdentifier::tryParse('0088:'.$id) !== null
                ? []
                : [
                    'field' => 'routing_id',
                    'label' => "routing_id GLN must be 0088: followed by exactly 13 digits. Got \"{$scheme}:{$id}\".",
                ];
        }

        if (!$this->identifierValidator()->validFormat($scheme, $id, checkDigit: false)) {
            return [
                'field' => 'routing_id',
                'label' => ctrans('texts.routing_id') . " {$scheme}:{$id} does not match the expected format for {$scheme}.",
            ];
        }

        return []; // valid
    }

    private function identifierValidator(): StorecoveIdentifierValidator
    {
        return $this->identifierValidator ??= new StorecoveIdentifierValidator();
    }

    private function testCompanyState(mixed $entity): array
    {

        $client = false;
        $vendor = false;
        $settings_object = false;
        $company = false;

        if ($entity instanceof Client) {
            $client = $entity;
            $company = $entity->company;
            $settings_object = $client;
        } elseif ($entity instanceof Company) {
            $company = $entity;
            $settings_object = $company;
        } elseif ($entity instanceof Vendor) {
            $vendor = $entity;
            $company = $entity->company;
            $settings_object = $company;
        } elseif ($entity instanceof Invoice || $entity instanceof Credit || $entity instanceof Quote) {
            $client = $entity->client;
            $company = $entity->company;
            $settings_object = $entity->client;
        } elseif ($entity instanceof PurchaseOrder) {
            $vendor = $entity->vendor;
            $company = $entity->company;
            $settings_object = $company;
        }

        $errors = [];

        foreach ($this->company_settings_fields as $field) {

            if ($this->validString($settings_object->getSetting($field))) {
                continue;
            }

            $errors[] = ['field' => $field, 'label' => ctrans("texts.{$field}")];

        }

        //test legal entity id present
        if (intval($company->legal_entity_id) == 0) {
            $errors[] = ['field' => "You have not registered a legal entity id as yet."];
        }

        //If not an individual, you MUST have a VAT number
        if (!in_array($company->getSetting('classification'), ['other', 'individual']) && !$this->validString($company->getSetting('vat_number'))) {
            $errors[] = ['field' => 'vat_number', 'label' => ctrans("texts.vat_number")];
        }

        return $errors;

    }

    // private function testInvoiceState($entity): array
    // {
    //     $errors = [];

    //     foreach($this->invoice_fields as $field)
    //     {

    //     }

    //     return $errors;
    // }

    // private function testVendorState(): array
    // {

    // }


    /************************************ helpers ************************************/
    private function validString(?string $string): bool
    {
        return iconv_strlen($string ?? '') >= 1;
    }

    private function checkNexus(Client $client): self
    {

        $company_country_code = $client->company->country()->iso_3166_2;
        $client_country_code = $client->country->iso_3166_2;
        $br = new \App\DataMapper\Tax\BaseRule();
        $eu_countries = $br->eu_country_codes;

        if ($client_country_code == $company_country_code) {
        } elseif (in_array($company_country_code, $eu_countries) && !in_array($client_country_code, $eu_countries)) {
        } elseif (in_array($client_country_code, $eu_countries)) {

            // First, determine if we're over threshold
            $is_over_threshold = isset($client->company->tax_data->regions->EU->has_sales_above_threshold)
                               && $client->company->tax_data->regions->EU->has_sales_above_threshold;

            // Is this B2B or B2C?
            $is_b2c = strlen($client->vat_number ?? '') < 2
                    || !($client->has_valid_vat_number ?? false)
                    || $client->classification == 'individual';

            // B2C, under threshold, no Company VAT Registerd - must charge origin country VAT
            if ($is_b2c && !$is_over_threshold && strlen($client->company->settings->vat_number ?? '') < 2) {

            } elseif ($is_b2c) {
                if ($is_over_threshold) {
                    // B2C over threshold - need destination VAT number
                    if (!isset($client->company->tax_data->regions->EU->subregions->{$client_country_code}->vat_number)) {
                        $this->errors['invoice'][] = "Tax Nexus is client country ({$client_country_code}) - however VAT number not present for this region.";
                    }
                }

            } elseif ($is_over_threshold && !in_array($company_country_code, $eu_countries)) {

            }


        }

        return $this;
    }


}
