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

namespace Tests\Integration\Einvoice\Storecove;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Country;
use App\Models\Product;
use Tests\MockAccountData;
use App\Models\ClientContact;
use App\DataMapper\InvoiceItem;
use App\DataMapper\Tax\TaxModel;
use App\DataMapper\CompanySettings;
use App\Services\EDocument\Standards\Peppol;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Repositories\CreditRepository;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Peppol credit-note XML for mixed line discounts on a positive credit (sign +1).
 *
 * Scenario:
 *   is_amount_discount=false, uses_inclusive_taxes=false, discount=0, amount=6092.90
 *   Line A — control (no line discount)
 *   Line B — percentage discount on a positive row
 *   Line C — percentage discount on a negative-quantity offset row
 */
class PeppolCreditNoteDiscountTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private bool $hasSaxon = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test for GH Actions');
        }

        try {
            new \Saxon\SaxonProcessor();
            $this->hasSaxon = true;
        } catch (\Throwable) {
            $this->hasSaxon = false;
        }

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->setupCompany();
    }

    private function setupCompany(): void
    {
        $settings = CompanySettings::defaults();
        $settings->vat_number = 'DE923356489';
        $settings->id_number = '01234567890';
        $settings->classification = 'business';
        $settings->country_id = Country::where('iso_3166_2', 'DE')->first()->id;
        $settings->email = uniqid('peppol-discount') . '@example.com';
        $settings->currency_id = '3';

        $tax_data = new TaxModel();
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->seller_subregion = 'DE';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new \InvoiceNinja\EInvoice\Models\Peppol\BranchType\FinancialInstitutionBranch();
        $fib->ID = 'DEUTDEMMXXX';

        $pfa = new \InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount();
        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $id->value = 'DE89370400440532013000';
        $pfa->ID = $id;
        $pfa->Name = 'Test account';
        $pfa->FinancialInstitutionBranch = $fib;

        $pm = new \InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;
        $pmc = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode();
        $pmc->value = '30';
        $pm->PaymentMeansCode = $pmc;
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

    private function createClient(): Client
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => Country::where('iso_3166_2', 'DE')->first()->id,
            'vat_number' => 'DE173755434',
            'classification' => 'business',
            'has_valid_vat_number' => true,
            'name' => 'Peppol Discount Client',
            'address1' => 'Test Address',
            'city' => 'Berlin',
            'postal_code' => '10115',
        ]);

        ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $client->company_id,
            'user_id' => $client->user_id,
            'email' => uniqid('peppol-discount') . '@example.com',
        ]);

        return $client;
    }

    /**
     * @return array<int, InvoiceItem>
     */
    private function scenarioLineItems(): array
    {
        return [
            $this->lineItem('A', 9490, 1, 0),
            $this->lineItem('B', 100, 2, 10),
            $this->lineItem('C', 4590, -1, 10),
        ];
    }

    private function lineItem(string $productKey, float $cost, float $quantity, float $discount): InvoiceItem
    {
        $item = new InvoiceItem();
        $item->product_key = $productKey;
        $item->notes = "Line {$productKey}";
        $item->quantity = $quantity;
        $item->cost = $cost;
        $item->discount = $discount;
        $item->is_amount_discount = false;
        $item->tax_id = (string) Product::PRODUCT_TYPE_PHYSICAL;
        $item->tax_name1 = 'VAT';
        $item->tax_rate1 = 10;
        $item->tax_name2 = '';
        $item->tax_rate2 = 0;
        $item->tax_name3 = '';
        $item->tax_rate3 = 0;
        $item->type_id = '1';
        $item->unit_code = 'C62';

        return $item;
    }

    private function createScenarioCredit(Client $client): Credit
    {
        $credit = Credit::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'is_amount_discount' => false,
            'po_number' => 'PEPPOL-CN-DISC',
            'line_items' => $this->scenarioLineItems(),
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
        ]);

        $credit = $credit->calc()->getCredit();
        $credit = (new CreditRepository())->save([], $credit);
        $credit = $credit->service()->markSent()->save();

        return $credit;
    }

    /**
     * @return array{credit: Credit, peppol: Peppol, xml: string, document: object, lines: array<int, array<string, mixed>>}
     */
    private function buildScenario(): array
    {
        $client = $this->createClient();
        $credit = $this->createScenarioCredit($client);

        $peppol = (new Peppol($credit))->run();
        $errors = $peppol->getErrors();
        $this->assertEmpty($errors, 'Peppol pipeline errors: ' . implode('; ', $errors));

        $xml = $peppol->toXml();
        $this->assertNotEmpty($xml);
        $this->assertStringContainsString('CreditNote', $xml);

        return [
            'credit' => $credit,
            'peppol' => $peppol,
            'xml' => $xml,
            'document' => $peppol->getDocument(),
            'lines' => $this->parseCreditNoteLines($xml),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseCreditNoteLines(string $xml): array
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        $lines = [];

        foreach ($xpath->query('//cac:CreditNoteLine') as $lineNode) {
            $line = [];

            $line['id'] = $this->xpathText($xpath, 'cbc:ID', $lineNode);
            $line['name'] = $this->xpathText($xpath, 'cac:Item/cbc:Name', $lineNode);
            $line['quantity'] = (float) $this->xpathText($xpath, 'cbc:CreditedQuantity', $lineNode);
            $line['line_extension_amount'] = (float) $this->xpathText($xpath, 'cbc:LineExtensionAmount', $lineNode);
            $line['price_amount'] = (float) $this->xpathText($xpath, 'cac:Price/cbc:PriceAmount', $lineNode);

            $acNode = $xpath->query('cac:AllowanceCharge', $lineNode)->item(0);
            if ($acNode) {
                $line['allowance'] = [
                    'amount' => (float) $this->xpathText($xpath, 'cbc:Amount', $acNode),
                    'base_amount' => (float) $this->xpathText($xpath, 'cbc:BaseAmount', $acNode),
                    'multiplier' => (float) $this->xpathText($xpath, 'cbc:MultiplierFactorNumeric', $acNode),
                    'charge_indicator' => $this->xpathText($xpath, 'cbc:ChargeIndicator', $acNode),
                ];
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private function xpathText(\DOMXPath $xpath, string $query, \DOMNode $context): string
    {
        $node = $xpath->query($query, $context)->item(0);

        return $node ? trim($node->nodeValue) : '';
    }

    /**
     * PEPPOL-EN16931-R120: qty × price + charges − allowances ≈ line extension.
     */
    private function lineNetResidual(array $line): float
    {
        $adjustment = $line['allowance']['amount'] ?? 0.0;
        $isCharge = ($line['allowance']['charge_indicator'] ?? 'false') === 'true';

        return ($line['quantity'] * $line['price_amount'])
            + ($isCharge ? $adjustment : 0.0)
            - (!$isCharge ? $adjustment : 0.0)
            - $line['line_extension_amount'];
    }

    private function parseDocumentTotals(string $xml): array
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

    private function validateXslt(string $xml): void
    {
        if (!$this->hasSaxon) {
            $this->markTestSkipped('Saxon not installed');
        }

        $validator = new XsltDocumentValidator($xml);
        $validator->validate();

        $messages = [];
        foreach ($validator->getErrors() as $category => $categoryMessages) {
            foreach ($categoryMessages as $message) {
                $messages[] = "[{$category}] {$message}";
            }
        }

        $this->assertEmpty($messages, "XSLT validation errors:\n" . implode("\n", $messages));
    }

    public function testScenarioCreditAmountAndLineTotals(): void
    {
        $scenario = $this->buildScenario();
        $credit = $scenario['credit'];

        $this->assertSame(false, (bool) $credit->is_amount_discount);
        $this->assertSame(false, (bool) $credit->uses_inclusive_taxes);
        $this->assertEqualsWithDelta(0.0, (float) $credit->discount, 0.001);
        $this->assertGreaterThan(0, (float) $credit->amount, 'Positive credit => sign +1');
        $this->assertEqualsWithDelta(6092.90, (float) $credit->amount, 0.01);

        $items = $credit->line_items;
        $this->assertEqualsWithDelta(9490.0, (float) $items[0]->line_total, 0.01, 'Control row A');
        $this->assertEqualsWithDelta(180.0, (float) $items[1]->line_total, 0.01, 'Discounted row B');
        $this->assertEqualsWithDelta(-4131.0, (float) $items[2]->line_total, 0.01, 'Discounted offset row C');
    }

    public function testControlRowGeneratesUndiscountedCreditNoteLine(): void
    {
        $line = $this->buildScenario()['lines'][0];

        $this->assertSame('A', $line['name']);
        $this->assertEqualsWithDelta(1.0, $line['quantity'], 0.001);
        $this->assertEqualsWithDelta(9490.0, $line['price_amount'], 0.01);
        $this->assertEqualsWithDelta(9490.0, $line['line_extension_amount'], 0.01);
        $this->assertGreaterThan(0, $line['price_amount'], 'BR-27: unit price must be non-negative');
        $this->assertArrayNotHasKey('allowance', $line);
        $this->assertEqualsWithDelta(0.0, $this->lineNetResidual($line), 0.02, 'PEPPOL-EN16931-R120');
    }

    public function testDiscountedPositiveRowGeneratesAllowanceAndReconciles(): void
    {
        $line = $this->buildScenario()['lines'][1];

        $this->assertSame('B', $line['name']);
        $this->assertEqualsWithDelta(2.0, $line['quantity'], 0.001, 'CreditedQuantity must remain the invoiced quantity when discount is on AllowanceCharge');
        $this->assertGreaterThan(0, $line['price_amount'], 'BR-27: unit price must be non-negative');
        $this->assertArrayHasKey('allowance', $line);
        $this->assertSame('false', $line['allowance']['charge_indicator']);
        $this->assertEqualsWithDelta(10.0, $line['allowance']['multiplier'], 0.01);
        $this->assertEqualsWithDelta(200.0, $line['allowance']['base_amount'], 0.01);
        $this->assertEqualsWithDelta(20.0, $line['allowance']['amount'], 0.01);
        $this->assertEqualsWithDelta(180.0, $line['line_extension_amount'], 0.01);
        $this->assertEqualsWithDelta(0.0, $this->lineNetResidual($line), 0.02, 'PEPPOL-EN16931-R120');
    }

    public function testDiscountedNegativeQuantityOffsetRowGeneratesValidCreditNoteLine(): void
    {
        $line = $this->buildScenario()['lines'][2];

        $this->assertSame('C', $line['name']);
        $this->assertEqualsWithDelta(-1.0, $line['quantity'], 0.001, 'Offset qty must stay negative; discount is a line charge (R040/R120)');
        $this->assertGreaterThan(0, $line['price_amount'], 'BR-27: unit price must be non-negative');
        $this->assertLessThan(0, $line['line_extension_amount'], 'Offset row reduces the credit total');
        $this->assertEqualsWithDelta(-4131.0, $line['line_extension_amount'], 0.01);
        $this->assertArrayHasKey('allowance', $line);
        $this->assertSame('true', $line['allowance']['charge_indicator'], 'Negative qty discount must be ChargeIndicator=true at line level (R044 allows this outside Price)');
        $this->assertEqualsWithDelta(10.0, $line['allowance']['multiplier'], 0.01);
        $this->assertEqualsWithDelta(4590.0, $line['allowance']['base_amount'], 0.01);
        $this->assertEqualsWithDelta(459.0, $line['allowance']['amount'], 0.01, 'PEPPOL-EN16931-R040: Amount = BaseAmount × 10%');
        $this->assertEqualsWithDelta(0.0, $this->lineNetResidual($line), 0.02, 'PEPPOL-EN16931-R120');
    }

    public function testDocumentTotalsMatchCreditAmount(): void
    {
        $scenario = $this->buildScenario();
        $totals = $this->parseDocumentTotals($scenario['xml']);

        $lineSum = array_sum(array_column($scenario['lines'], 'line_extension_amount'));

        $this->assertEqualsWithDelta(5539.0, $lineSum, 0.01, '9490 + 180 − 4131');
        $this->assertEqualsWithDelta(5539.0, $totals['line_extension'], 0.01);
        $this->assertEqualsWithDelta(5539.0, $totals['tax_exclusive'], 0.01);
        $this->assertEqualsWithDelta(553.90, $totals['tax_amount'], 0.01);
        $this->assertEqualsWithDelta(6092.90, $totals['tax_inclusive'], 0.01);
        $this->assertEqualsWithDelta(6092.90, $totals['payable'], 0.01);
        $this->assertEqualsWithDelta(6092.90, (float) $scenario['credit']->amount, 0.01);
    }

    public function testPeppolXmlPassesEn16931Validation(): void
    {
        $scenario = $this->buildScenario();
        $this->validateXslt($scenario['xml']);
    }

    public function testPeppolXmlPassesUblCreditNoteXsd(): void
    {
        $scenario = $this->buildScenario();
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($scenario['xml']), 'Generated output must be well-formed XML');

        libxml_use_internal_errors(true);
        $valid = $dom->schemaValidate(app_path(
            'Services/EDocument/Standards/Validation/Peppol/Stylesheets/UBL2.1/UBL-CreditNote-2.1.xsd'
        ));
        $errors = array_map(
            static fn(\LibXMLError $error): string => sprintf('Line %d: %s', $error->line, trim($error->message)),
            libxml_get_errors()
        );
        libxml_clear_errors();

        $this->assertTrue($valid, "UBL CreditNote XSD errors:\n" . implode("\n", $errors));
    }

    public function testStorecovePayloadLinesReconcileForDiscountedCredit(): void
    {
        $scenario = $this->buildScenario();

        $storecove = new Storecove();
        $storecove->adapter
            ->transformFromPeppol($scenario['credit'], $scenario['document'], $scenario['peppol']->getDocumentKind(), $scenario['xml'])
            ->decorate();

        $result = $storecove->adapter->getDocument();
        $this->assertEmpty($result['errors'], 'Storecove transform errors: ' . json_encode($result['errors']));

        $doc = $result['document'];
        $this->assertEqualsWithDelta(-6092.90, $doc['amount_including_vat'], 0.05);

        foreach ($doc['invoice_lines'] as $index => $wireLine) {
            $allowance = 0.0;
            foreach ($wireLine['allowance_charges'] ?? [] as $ac) {
                $allowance += $ac['amount_excluding_tax'] ?? 0.0;
            }

            $residual = ($wireLine['item_price'] * $wireLine['quantity']) + $allowance - $wireLine['amount_excluding_vat'];

            $this->assertLessThanOrEqual(
                0.02,
                abs($residual),
                "Storecove line {$index} must reconcile. Residual {$residual}. Line: " . json_encode($wireLine)
            );
        }

        $this->assertEqualsWithDelta(2.0, $doc['invoice_lines'][1]['quantity'], 0.001, 'Discounted row B must keep quantity 2 on the wire');
        $this->assertEqualsWithDelta(1.0, $doc['invoice_lines'][2]['quantity'], 0.001, 'Offset row C must stay positive qty with signed unit price on the wire');
    }
}
