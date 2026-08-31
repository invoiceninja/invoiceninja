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

use App\DataMapper\CompanySettings;
use App\DataMapper\InvoiceItem;
use App\DataMapper\Tax\TaxModel;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Country;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Standards\Peppol;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class FranceAfnorBillingContextTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const LINE_TYPE_GOODS = 1;
    private const LINE_TYPE_SERVICE = 2;
    private const LINE_TYPE_LATE_FEE = 5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->configureCompany('FR');
    }

    public static function billingContextProvider(): array
    {
        return [
            'goods' => [[self::LINE_TYPE_GOODS], 'B1'],
            'services' => [[self::LINE_TYPE_SERVICE], 'S1'],
            'mixed goods and services' => [[self::LINE_TYPE_GOODS, self::LINE_TYPE_SERVICE], 'M1'],
            'service with a late fee remains a service' => [[self::LINE_TYPE_SERVICE, self::LINE_TYPE_LATE_FEE], 'S1'],
            'unknown line uses the goods default' => [[999], 'B1'],
        ];
    }

    #[DataProvider('billingContextProvider')]
    public function testFrenchBusinessInvoiceSerializesInferredBillingContext(array $lineTypes, string $expectedCode): void
    {
        $client = $this->createClient('FR');
        $lineItems = array_map(fn (int $lineType): InvoiceItem => $this->lineItem($lineType), $lineTypes);

        $document = $this->wireDocument($this->createInvoice($client, $lineItems));

        $this->assertSame($expectedCode, $document['fr_cadre_de_facturation']);
        $this->assertArrayNotHasKey('frCadreDeFacturation', $document);
    }

    public function testZeroValueServiceLineIsStillClassifiedAsAService(): void
    {
        $client = $this->createClient('FR');
        $document = $this->wireDocument(
            $this->createInvoice($client, [$this->lineItem(self::LINE_TYPE_SERVICE, 0)]),
        );

        $this->assertSame('S1', $document['fr_cadre_de_facturation']);
    }

    public function testCreditsAndNegativeInvoicesReceiveTheInferredBillingContext(): void
    {
        $client = $this->createClient('FR');

        $credit = $this->createCredit($client, [$this->lineItem(self::LINE_TYPE_GOODS)]);
        $negativeInvoice = $this->createInvoice($client, [$this->lineItem(self::LINE_TYPE_SERVICE, -100)]);

        $this->assertSame('B1', $this->wireDocument($credit)['fr_cadre_de_facturation']);
        $this->assertSame('S1', $this->wireDocument($negativeInvoice)['fr_cadre_de_facturation']);
    }

    public function testBillingContextIsLimitedToTheExistingDgfipScope(): void
    {
        $individual = $this->createClient('FR', 'individual');
        $foreignBusiness = $this->createClient('DE');

        $individualDocument = $this->wireDocument(
            $this->createInvoice($individual, [$this->lineItem(self::LINE_TYPE_GOODS)]),
        );
        $foreignDocument = $this->wireDocument(
            $this->createInvoice($foreignBusiness, [$this->lineItem(self::LINE_TYPE_GOODS)]),
        );

        $this->assertArrayNotHasKey('fr_cadre_de_facturation', $individualDocument);
        $this->assertArrayNotHasKey('fr_cadre_de_facturation', $foreignDocument);
    }

    public function testForeignSellerWithFrenchVatNexusReceivesBillingContext(): void
    {
        $this->configureCompany('DE', 'FR44732829320');
        $client = $this->createClient('FR');

        $document = $this->wireDocument(
            $this->createInvoice($client, [$this->lineItem(self::LINE_TYPE_SERVICE)]),
        );

        $this->assertSame('S1', $document['fr_cadre_de_facturation']);
    }

    public function testForeignSellerWithoutFrenchVatNexusDoesNotReceiveBillingContext(): void
    {
        $this->configureCompany('DE');
        $client = $this->createClient('FR');

        $document = $this->wireDocument(
            $this->createInvoice($client, [$this->lineItem(self::LINE_TYPE_SERVICE)]),
        );

        $this->assertArrayNotHasKey('fr_cadre_de_facturation', $document);
    }

    private function configureCompany(string $countryCode, ?string $frenchVatNumber = null): void
    {
        $settings = CompanySettings::defaults();
        $settings->vat_number = $countryCode === 'FR' ? 'FR44732829320' : 'DE923356489';
        $settings->id_number = $countryCode === 'FR' ? '73282932000074' : '01234567890';
        $settings->classification = 'business';
        $settings->country_id = Country::where('iso_3166_2', $countryCode)->firstOrFail()->id;
        $settings->email = uniqid('testuser') . '@gmail.com';
        $settings->currency_id = '3';

        $taxData = new TaxModel();
        $taxData->regions->EU->has_sales_above_threshold = false;
        $taxData->regions->EU->tax_all_subregions = true;
        $taxData->seller_subregion = $countryCode;
        $taxData->regions->EU->subregions->FR->vat_number = $frenchVatNumber ?? '';

        $peppolInvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();
        $paymentMeans = new \InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans();
        $paymentMeansCode = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode();
        $paymentMeansCode->value = '30';
        $paymentMeans->PaymentMeansCode = $paymentMeansCode;
        $peppolInvoice->PaymentMeans[] = $paymentMeans;

        $eInvoice = new \stdClass();
        $eInvoice->Invoice = $peppolInvoice;

        $this->company->settings = $settings;
        $this->company->tax_data = $taxData;
        $this->company->calculate_taxes = true;
        $this->company->legal_entity_id = 290868;
        $this->company->e_invoice = $eInvoice;
        $this->company->save();
    }

    private function createClient(string $countryCode, string $classification = 'business'): Client
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => Country::where('iso_3166_2', $countryCode)->firstOrFail()->id,
            'vat_number' => $countryCode === 'FR' ? 'FR44732829320' : 'DE173755434',
            'id_number' => $countryCode === 'FR' ? '73282932000074' : '01234567890',
            'classification' => $classification,
            'has_valid_vat_number' => true,
            'name' => 'Test Client',
        ]);

        ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $client->company_id,
            'user_id' => $client->user_id,
            'email' => uniqid('testuser') . '@gmail.com',
        ]);

        return $client;
    }

    private function lineItem(int $lineType, float $cost = 100): InvoiceItem
    {
        $item = new InvoiceItem();
        $item->product_key = 'Test line';
        $item->notes = 'AFNOR billing-context test';
        $item->quantity = 1;
        $item->cost = $cost;
        $item->type_id = (string) $lineType;
        $item->tax_id = (string) Product::PRODUCT_TYPE_PHYSICAL;
        $item->tax_name1 = 'TVA';
        $item->tax_rate1 = 20;
        $item->unit_code = 'C62';

        return $item;
    }

    private function baseAttributes(Client $client, array $lineItems): array
    {
        return [
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
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
        ];
    }

    private function createInvoice(Client $client, array $lineItems): Invoice
    {
        return Invoice::factory()->create($this->baseAttributes($client, $lineItems))->calc()->getInvoice();
    }

    private function createCredit(Client $client, array $lineItems): Credit
    {
        return Credit::factory()->create($this->baseAttributes($client, $lineItems))->calc()->getCredit();
    }

    private function wireDocument(Invoice|Credit $sourceDocument): array
    {
        $peppol = (new Peppol($sourceDocument))->run();
        $storecove = new Storecove();

        $storecove->adapter
            ->transformFromPeppol($sourceDocument, $peppol->getDocument(), $peppol->isCreditNote())
            ->decorate();

        $result = $storecove->adapter->getDocument();

        $this->assertEmpty($result['errors'], 'Storecove transform errors: ' . json_encode($result['errors']));

        return $result['document'];
    }
}
