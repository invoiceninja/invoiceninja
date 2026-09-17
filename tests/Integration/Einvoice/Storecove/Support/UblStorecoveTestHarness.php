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

namespace Tests\Integration\Einvoice\Storecove\Support;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\ClientContact;
use App\Models\CreditInvitation;
use App\DataMapper\InvoiceItem;
use App\DataMapper\Tax\TaxModel;
use App\DataMapper\CompanySettings;
use App\Services\EDocument\Standards\Peppol;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;
use App\Repositories\CreditRepository;
use App\Repositories\InvoiceRepository;

/**
 * Shared helpers for UBL-first Storecove regression tests.
 *
 * Validates the contract:
 *   validated UBL XML → production pipeline → wire document
 * Wire expectations are derived from parsed UBL fields, never from calc().
 */
trait UblStorecoveTestHarness
{
    private const SLACK = 0.02;

    private bool $harnessHasSaxon = false;

    protected function bootUblStorecoveHarness(): void
    {
        try {
            new \Saxon\SaxonProcessor();
            $this->harnessHasSaxon = true;
        } catch (\Throwable) {
            $this->harnessHasSaxon = false;
        }

        $this->setupHarnessCompany();
    }

    private function setupHarnessCompany(): void
    {
        $settings = CompanySettings::defaults();
        $settings->vat_number = 'DE923356489';
        $settings->id_number = '01234567890';
        $settings->classification = 'business';
        $settings->country_id = Country::where('iso_3166_2', 'DE')->first()->id;
        $settings->email = uniqid('ubl-storecove') . '@gmail.com';
        $settings->currency_id = '3';
        $settings->e_invoice_type = 'PEPPOL';

        $tax_data = new TaxModel();
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->seller_subregion = 'DE';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();
        $pm = new \InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans();
        $pmc = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode();
        $pmc->value = '30';
        $pm->PaymentMeansCode = $pmc;

        // BR-61: payment means code 30 (credit transfer) requires the payment
        // account identifier (BT-84). Without it every document built by this
        // harness fails the schematron for a reason unrelated to the test.
        $pfa = new \InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount();
        $pfaId = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $pfaId->value = 'DE89370400440532013000';
        $pfa->ID = $pfaId;
        $pfa->Name = 'UBL Storecove Harness';
        $pm->PayeeFinancialAccount = $pfa;

        $einvoice->PaymentMeans[] = $pm;

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $this->company->settings = $settings;
        $this->company->tax_data = $tax_data;
        $this->company->calculate_taxes = false;
        $this->company->legal_entity_id = 290868;
        $this->company->e_invoice = $stub;
        $this->company->save();
    }

    protected function harnessClient(): Client
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => Country::where('iso_3166_2', 'DE')->first()->id,
            'vat_number' => 'DE173755434',
            'classification' => 'business',
            'has_valid_vat_number' => true,
            'name' => 'UBL Storecove Client',
            'address1' => 'Test Address',
            'city' => 'Berlin',
            'postal_code' => '10115',
        ]);

        ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $client->company_id,
            'user_id' => $client->user_id,
            'email' => uniqid('ubl-storecove') . '@gmail.com',
        ]);

        return $client;
    }

    protected function harnessLineItem(
        string $productKey,
        float $cost,
        float $quantity,
        float $discount = 0,
        float $taxRate = 10,
        bool $isAmountDiscount = false,
    ): InvoiceItem {
        $item = new InvoiceItem();
        $item->product_key = $productKey;
        $item->notes = "Line {$productKey}";
        $item->quantity = $quantity;
        $item->cost = $cost;
        $item->discount = $discount;
        $item->is_amount_discount = $isAmountDiscount;
        $item->tax_id = '1';
        $item->tax_name1 = 'VAT';
        $item->tax_rate1 = $taxRate;
        $item->tax_name2 = '';
        $item->tax_rate2 = 0;
        $item->tax_name3 = '';
        $item->tax_rate3 = 0;
        $item->type_id = '1';
        $item->unit_code = 'C62';

        return $item;
    }

    /**
     * @param  array<int, InvoiceItem>  $lineItems
     */
    protected function harnessCredit(Client $client, array $lineItems, bool $markSent = false): Credit
    {
        $credit = Credit::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'is_amount_discount' => false,
            'line_items' => $lineItems,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
        ]);

        CreditInvitation::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_contact_id' => $client->contacts()->first()->id,
            'credit_id' => $credit->id,
        ]);

        $credit = $this->harnessSaveCredit($credit);

        if ($markSent) {
            $credit = $credit->service()->markSent()->save();
        }

        return $credit->fresh(['invitations']);
    }

    /**
     * Persist credit changes through the repository so Peppol surcharge-tax
     * defaults (e_invoice_type=PEPPOL → custom_surcharge_taxN=true) apply before calc.
     */
    protected function harnessSaveCredit(Credit $credit): Credit
    {
        return (new CreditRepository())->save([], $credit);
    }

    /**
     * @param  array<int, InvoiceItem>  $lineItems
     */
    protected function harnessInvoice(Client $client, array $lineItems): Invoice
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'due_date' => now()->addDays(2)->format('Y-m-d'),
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'is_amount_discount' => false,
            'line_items' => $lineItems,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
        ]);

        return (new InvoiceRepository())->save([], $invoice);
    }

    /**
     * @return array{
     *   model: Invoice|Credit,
     *   peppol: Peppol,
     *   xml: string,
     *   ubl_lines: array<int, array<string, mixed>>,
     *   ubl_totals: array<string, float>,
     *   is_credit_note: bool
     * }
     */
    protected function buildUbl(Invoice|Credit $model): array
    {
        $peppol = (new Peppol($model))->run();
        $errors = $peppol->getErrors();
        $this->assertEmpty($errors, 'Peppol pipeline errors: ' . implode('; ', $errors));

        $xml = $peppol->toXml();
        $this->assertNotEmpty($xml);

        $isCreditNote = $peppol->isCreditNote();

        return [
            'model' => $model,
            'peppol' => $peppol,
            'xml' => $xml,
            'ubl_lines' => $this->parseUblLines($xml, $isCreditNote),
            'ubl_totals' => $this->parseUblDocumentTotals($xml),
            'is_credit_note' => $isCreditNote,
        ];
    }

    /**
     * Production pipeline: Peppol → transformFromPeppol → decorate → getDocument().
     *
     * @return array{document: array<string, mixed>, errors: array<int, string>}
     */
    protected function buildWire(Invoice|Credit $model, Peppol $peppol, ?string $validatedUblXml = null): array
    {
        $storecove = new Storecove();
        $storecove->adapter
            ->transformFromPeppol(
                $model,
                $peppol->getDocument(),
                $peppol->getDocumentKind(),
                $validatedUblXml ?? $peppol->toXml(),
            )
            ->decorate();

        $result = $storecove->adapter->getDocument();
        $this->assertEmpty($result['errors'], 'Storecove transform errors: ' . json_encode($result['errors']));

        return $result;
    }

    protected function assertUblPassesSchematron(string $xml, string $context = ''): void
    {
        if (!$this->harnessHasSaxon) {
            $this->markTestSkipped('Saxon not installed — schematron assertion skipped');
        }

        $validator = new XsltDocumentValidator($xml);
        $validator->validate();

        // XsltDocumentValidator emits the raw schematron assertion text with no
        // severity marker (see its validateSchema()), so filtering on
        // [fatal]/[error] discarded every message and this gate never failed.
        // Production EntityLevel treats every message as an error; match it.
        $messages = [];
        foreach (['xsd', 'stylesheet', 'general'] as $category) {
            foreach ($validator->getErrors()[$category] ?? [] as $msg) {
                $messages[] = "[{$category}] {$msg}";
            }
        }

        $prefix = $context !== '' ? "{$context}: " : '';
        $this->assertEmpty($messages, $prefix . "Schematron errors:\n" . implode("\n", $messages));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function parseUblLines(string $xml, bool $isCreditNote): array
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        $lineTag = $isCreditNote ? 'CreditNoteLine' : 'InvoiceLine';
        $qtyTag = $isCreditNote ? 'cbc:CreditedQuantity' : 'cbc:InvoicedQuantity';
        $lines = [];

        foreach ($xpath->query("//cac:{$lineTag}") as $lineNode) {
            $line = [
                'id' => $this->xpathText($xpath, 'cbc:ID', $lineNode),
                'name' => $this->xpathText($xpath, 'cac:Item/cbc:Name', $lineNode),
                'quantity' => (float) $this->xpathText($xpath, $qtyTag, $lineNode),
                'line_extension_amount' => (float) $this->xpathText($xpath, 'cbc:LineExtensionAmount', $lineNode),
                'price_amount' => (float) $this->xpathText($xpath, 'cac:Price/cbc:PriceAmount', $lineNode),
            ];

            $acNode = $xpath->query('cac:AllowanceCharge', $lineNode)->item(0);
            if ($acNode) {
                $line['allowance_charge'] = [
                    'amount' => (float) $this->xpathText($xpath, 'cbc:Amount', $acNode),
                    'base_amount' => (float) $this->xpathText($xpath, 'cbc:BaseAmount', $acNode),
                    'multiplier' => (float) $this->xpathText($xpath, 'cbc:MultiplierFactorNumeric', $acNode),
                    'charge_indicator' => $this->xpathText($xpath, 'cbc:ChargeIndicator', $acNode) ?: 'false',
                    'reason' => $this->xpathText($xpath, 'cbc:AllowanceChargeReason', $acNode),
                ];
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @return array<string, float>
     */
    protected function parseUblDocumentTotals(string $xml): array
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        return [
            'line_extension' => (float) $this->xpathText($xpath, '//cac:LegalMonetaryTotal/cbc:LineExtensionAmount', $dom),
            'tax_exclusive' => (float) $this->xpathText($xpath, '//cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount', $dom),
            'tax_inclusive' => (float) $this->xpathText($xpath, '//cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount', $dom),
            'payable' => (float) $this->xpathText($xpath, '//cac:LegalMonetaryTotal/cbc:PayableAmount', $dom),
            'tax_amount' => (float) $this->xpathText($xpath, '//cac:TaxTotal/cbc:TaxAmount', $dom),
        ];
    }

    /**
     * PEPPOL-EN16931-R120 on parsed UBL line.
     */
    protected function assertUblLineSatisfiesR120(array $ublLine, string $label = ''): void
    {
        $allowances = 0.0;
        $charges = 0.0;

        if (isset($ublLine['allowance_charge'])) {
            $ac = $ublLine['allowance_charge'];
            if (($ac['charge_indicator'] ?? 'false') === 'true') {
                $charges += $ac['amount'];
            } else {
                $allowances += $ac['amount'];
            }
        }

        $expected = ($ublLine['quantity'] * $ublLine['price_amount']) + $charges - $allowances;
        $prefix = $label !== '' ? "{$label}: " : '';

        $this->assertEqualsWithDelta(
            $expected,
            $ublLine['line_extension_amount'],
            self::SLACK,
            $prefix . 'UBL R120 failed'
        );
    }

    /**
     * PEPPOL-EN16931-R040 on parsed UBL allowance/charge when percentage present.
     */
    protected function assertUblLineSatisfiesR040(array $ublLine, string $label = ''): void
    {
        if (!isset($ublLine['allowance_charge'])) {
            return;
        }

        $ac = $ublLine['allowance_charge'];
        if (($ac['base_amount'] ?? 0) <= 0 || ($ac['multiplier'] ?? 0) <= 0) {
            return;
        }

        $prefix = $label !== '' ? "{$label}: " : '';
        $this->assertEqualsWithDelta(
            ($ac['base_amount'] * $ac['multiplier']) / 100,
            $ac['amount'],
            self::SLACK,
            $prefix . 'UBL R040 failed'
        );
    }

    /**
     * Credit-as-negative-invoice wire projection derived from UBL fields.
     *
     * @return array{quantity: float, item_price: float, amount_excluding_vat: float, amount_excluding_tax: float, allowance_sum: float}
     */
    protected function expectedCreditWireFromUbl(array $ublLine): array
    {
        $quantity = $ublLine['quantity'];
        $qtySign = $quantity < 0 ? -1.0 : 1.0;

        $wire = [
            'quantity' => abs($quantity),
            'item_price' => -($ublLine['price_amount'] * $qtySign),
            'amount_excluding_vat' => -$ublLine['line_extension_amount'],
            'amount_excluding_tax' => -$ublLine['line_extension_amount'],
            'allowance_sum' => 0.0,
        ];

        if (!isset($ublLine['allowance_charge'])) {
            return $wire;
        }

        $amount = abs($ublLine['allowance_charge']['amount']);
        $isCharge = ($ublLine['allowance_charge']['charge_indicator'] ?? 'false') === 'true';

        $wire['allowance_sum'] = $isCharge ? -$amount : $amount;

        return $wire;
    }

    /**
     * Positive invoice wire projection derived from UBL fields.
     *
     * @return array{quantity: float, item_price: float, amount_excluding_vat: float, allowance_sum: float}
     */
    protected function expectedInvoiceWireFromUbl(array $ublLine): array
    {
        $wire = [
            'quantity' => $ublLine['quantity'],
            'item_price' => $ublLine['price_amount'],
            'amount_excluding_vat' => $ublLine['line_extension_amount'],
            'allowance_sum' => 0.0,
        ];

        if (isset($ublLine['allowance_charge'])) {
            $wire['allowance_sum'] = -abs($ublLine['allowance_charge']['amount']);
        }

        return $wire;
    }

    /**
     * @param  array<string, mixed>  $wireLine
     */
    protected function wireAllowanceSum(array $wireLine): float
    {
        $sum = 0.0;
        foreach ($wireLine['allowance_charges'] ?? [] as $ac) {
            $sum += $ac['amount_excluding_tax'] ?? 0.0;
        }

        return $sum;
    }

    /**
     * Storecove line reconcile: itemPrice × qty + allowances − amount ≈ 0.
     *
     * @param  array<string, mixed>  $wireLine
     */
    protected function wireLineResidual(array $wireLine): float
    {
        $baseQty = $wireLine['base_quantity'] ?? 1.0;

        return ($wireLine['item_price'] * $wireLine['quantity'] / $baseQty)
            + $this->wireAllowanceSum($wireLine)
            - $wireLine['amount_excluding_vat'];
    }

    /**
     * @param  array<string, mixed>  $wireLine
     */
    protected function assertWireLineMatchesUblCreditMapping(array $ublLine, array $wireLine, string $label): void
    {
        $expected = $this->expectedCreditWireFromUbl($ublLine);

        $this->assertEqualsWithDelta($expected['quantity'], $wireLine['quantity'], 0.001, "{$label}: quantity");
        $this->assertEqualsWithDelta($expected['item_price'], $wireLine['item_price'], 0.01, "{$label}: item_price");
        $this->assertEqualsWithDelta($expected['amount_excluding_vat'], $wireLine['amount_excluding_vat'], 0.01, "{$label}: amount_excluding_vat");

        // amount_excluding_tax is optional in Storecove. If emitted, it is an
        // alias for the line amount and must not contain the unit price.
        if (array_key_exists('amount_excluding_tax', $wireLine)) {
            $this->assertEqualsWithDelta(
                $expected['amount_excluding_tax'],
                $wireLine['amount_excluding_tax'],
                0.01,
                "{$label}: amount_excluding_tax must equal the signed line amount",
            );
            $this->assertEqualsWithDelta(
                $wireLine['amount_excluding_vat'],
                $wireLine['amount_excluding_tax'],
                0.01,
                "{$label}: Storecove line amount aliases must agree",
            );
        }

        $this->assertEqualsWithDelta($expected['allowance_sum'], $this->wireAllowanceSum($wireLine), 0.01, "{$label}: allowance_charges sum");

        $this->assertLessThanOrEqual(
            self::SLACK,
            abs($this->wireLineResidual($wireLine)),
            "{$label}: Storecove line equation residual " . $this->wireLineResidual($wireLine)
        );
    }

    /**
     * @param  array<string, mixed>  $wireLine
     */
    protected function assertWireLineMatchesUblInvoiceMapping(array $ublLine, array $wireLine, string $label): void
    {
        $expected = $this->expectedInvoiceWireFromUbl($ublLine);

        $this->assertEqualsWithDelta($expected['quantity'], $wireLine['quantity'], 0.001, "{$label}: quantity");
        $this->assertEqualsWithDelta($expected['item_price'], $wireLine['item_price'], 0.01, "{$label}: item_price");
        $this->assertEqualsWithDelta($expected['amount_excluding_vat'], $wireLine['amount_excluding_vat'], 0.01, "{$label}: amount_excluding_vat");
        $this->assertEqualsWithDelta($expected['allowance_sum'], $this->wireAllowanceSum($wireLine), 0.01, "{$label}: allowance_charges sum");

        $this->assertLessThanOrEqual(
            self::SLACK,
            abs($this->wireLineResidual($wireLine)),
            "{$label}: Storecove line equation residual " . $this->wireLineResidual($wireLine)
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $ublLines
     * @param  array<int, array<string, mixed>>  $wireLines
     */
    protected function assertAllCreditWireLinesMatchUbl(array $ublLines, array $wireLines): void
    {
        $this->assertCount(count($ublLines), $wireLines, 'UBL and wire line counts must match');

        foreach ($ublLines as $i => $ublLine) {
            $label = $ublLine['name'] ?: ('line ' . ($i + 1));
            $this->assertUblLineSatisfiesR120($ublLine, $label);
            $this->assertUblLineSatisfiesR040($ublLine, $label);
            $this->assertWireLineMatchesUblCreditMapping($ublLine, $wireLines[$i], $label);
        }
    }

    /**
     * @param  array<string, float>  $ublTotals
     * @param  array<string, mixed>  $wireDoc
     */
    protected function assertCreditWireHeaderMatchesUbl(array $ublTotals, array $wireDoc, ?string $ublXml = null): void
    {
        $lineSum = array_sum(array_column($wireDoc['invoice_lines'], 'amount_excluding_vat'));

        $this->assertEqualsWithDelta(-$ublTotals['line_extension'], $lineSum, 0.05, 'Wire line sum vs UBL LineExtensionAmount');
        $this->assertEqualsWithDelta(-$ublTotals['tax_inclusive'], $wireDoc['amount_including_vat'], 0.05, 'Wire header vs UBL TaxInclusiveAmount');

        foreach ($wireDoc['tax_subtotals'] ?? [] as $subtotal) {
            $this->assertLessThanOrEqual(0, $subtotal['tax_amount'], 'Credit tax subtotal must be non-positive');
            $this->assertLessThanOrEqual(0, $subtotal['taxable_amount'], 'Credit taxable amount must be non-positive');
        }

        if ($ublXml !== null) {
            $this->assertCreditWireDocumentAllowancesMatchUbl($ublXml, $wireDoc);
        }
    }

    /**
     * @return array<int, array{amount: float, charge_indicator: string, reason: string}>
     */
    protected function parseUblDocumentAllowances(string $xml): array
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        $rootTag = $xpath->query('//*[local-name()="CreditNote" or local-name()="Invoice"]')->item(0);
        if (!$rootTag) {
            return [];
        }

        $allowances = [];
        foreach ($xpath->query('cac:AllowanceCharge', $rootTag) as $acNode) {
            $allowances[] = [
                'amount' => (float) trim($xpath->evaluate('string(cbc:Amount)', $acNode)),
                'charge_indicator' => trim($xpath->evaluate('string(cbc:ChargeIndicator)', $acNode)) ?: 'false',
                'reason' => trim($xpath->evaluate('string(cbc:AllowanceChargeReason)', $acNode)),
            ];
        }

        return $allowances;
    }

    /**
     * Credit-as-negative-invoice document-level allowance/charge projection from UBL.
     *
     * @param  array<int, array{amount: float, charge_indicator: string, reason: string}>  $ublDocAllowances
     * @return array<int, float>
     */
    protected function expectedCreditWireDocumentAllowanceAmounts(array $ublDocAllowances): array
    {
        $amounts = [];

        foreach ($ublDocAllowances as $allowance) {
            $amount = abs($allowance['amount']);
            $isCharge = ($allowance['charge_indicator'] ?? 'false') === 'true';
            $amounts[] = $isCharge ? -$amount : $amount;
        }

        sort($amounts);

        return $amounts;
    }

    /**
     * @param  array<string, mixed>  $wireDoc
     */
    protected function assertCreditWireDocumentAllowancesMatchUbl(string $ublXml, array $wireDoc): void
    {
        $expected = $this->expectedCreditWireDocumentAllowanceAmounts(
            $this->parseUblDocumentAllowances($ublXml)
        );

        $actual = array_map(
            fn ($ac) => (float) ($ac['amount_excluding_tax'] ?? 0),
            $wireDoc['allowance_charges'] ?? []
        );
        sort($actual);

        $this->assertEqualsWithDelta(
            $expected,
            $actual,
            0.01,
            'Wire document allowance_charges must match UBL document AllowanceCharge signs'
        );
    }

    private function xpathText(\DOMXPath $xpath, string $query, \DOMNode $context): string
    {
        $node = $xpath->query($query, $context)->item(0);

        return $node ? trim($node->nodeValue) : '';
    }
}
