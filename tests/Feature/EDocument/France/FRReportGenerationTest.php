<?php

namespace Tests\Feature\EDocument\France;

use App\DataMapper\CompanySettings;
use App\DataMapper\Tax\TaxModel;
use App\Factory\InvoiceItemFactory;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Country;
use App\Models\Invoice;
use App\Services\EDocument\Standards\France\Models\B2BIInvoice;
use App\Services\EDocument\Standards\France\Models\B2BIInvoiceLine;
use App\Services\EDocument\Standards\France\Models\B2BIParty;
use App\Services\EDocument\Standards\France\Models\B2BITaxSubtotal;
use App\Services\EDocument\Standards\Peppol;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvoiceNinja\EInvoice\EInvoice;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Tests\MockAccountData;
use Tests\TestCase;

class FRReportGenerationTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
        $this->makeTestData();
    }

    public function testItTransformsAPeppolInvoiceIntoTheFranceF10B2BIInvoiceModel(): void
    {
        $invoice = $this->makeFranceB2BIInvoice();
        $peppolInvoice = (new Peppol($invoice))->run()->getDocument();
        $context = [
            DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ];

        $eInvoice = new EInvoice();
        $serializer = $this->f10Serializer();
        $peppolJson = $eInvoice->encode($peppolInvoice, 'json', $context);
        $b2biInvoice = $serializer->deserialize($peppolJson, B2BIInvoice::class, 'json', $context);
        $normalized = $this->removeEmptyValues($b2biInvoice->toArray());
        $artifact = $this->storecoveF10Artifact($normalized);
        $artifactPath = base_path('tests/artifacts/fr_f10_b2bi_invoice_storecove_shape.json');

        $this->writeJsonArtifact($artifactPath, $artifact);

        $this->assertInstanceOf(B2BIInvoice::class, $b2biInvoice);
        $this->assertInstanceOf(B2BIParty::class, $b2biInvoice->accounting_supplier_party);
        $this->assertInstanceOf(B2BIParty::class, $b2biInvoice->accounting_customer_party);
        $this->assertContainsOnlyInstancesOf(B2BIInvoiceLine::class, $b2biInvoice->invoice_lines);
        $this->assertContainsOnlyInstancesOf(B2BITaxSubtotal::class, $b2biInvoice->tax_subtotals);
        $this->assertSame($invoice->number, $normalized['invoiceNumber']);
        $this->assertSame($invoice->date, $normalized['issueDate']);
        $this->assertSame('EUR', $normalized['documentCurrency']);
        $this->assertArrayHasKey('amountIncludingVat', $normalized);
        $this->assertGreaterThan(0, (float) $normalized['amountIncludingVat']);
        $this->assertArrayHasKey('accountingSupplierParty', $normalized);
        $this->assertArrayHasKey('accountingCustomerParty', $normalized);
        $this->assertArrayHasKey('invoiceLines', $normalized);
        $this->assertArrayHasKey('taxSubtotals', $normalized);
        $this->assertNotEmpty($normalized['invoiceLines']);
        $this->assertNotEmpty($normalized['taxSubtotals']);
        $this->assertFileExists($artifactPath);
        $this->assertSame($artifact, json_decode(file_get_contents($artifactPath), true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame('fr_e_report', $artifact['document']['documentType']);
        $this->assertSame($normalized, $artifact['document']['frEReport']['transactionReport']['b2biInvoices'][0]);
        $this->assertSame(1200, $normalized['amountIncludingVat']);
        $this->assertSame([
            [
                'taxCategory' => 'standard',
                'percentage' => 20,
                'taxableAmount' => 1000,
                'taxAmount' => 200,
                'country' => 'FR',
            ],
        ], $normalized['taxSubtotals']);
        $this->assertArrayHasKey('party', $normalized['accountingSupplierParty']);
        $this->assertArrayHasKey('publicIdentifiers', $normalized['accountingSupplierParty']);
        $this->assertArrayHasKey('party', $normalized['accountingCustomerParty']);
        $this->assertArrayHasKey('publicIdentifiers', $normalized['accountingCustomerParty']);
        $this->assertArrayHasKey('tax', $normalized['invoiceLines'][0]);
        $this->assertArrayNotHasKey('taxes', $normalized['invoiceLines'][0]);
    }

    private function makeFranceB2BIInvoice(): Invoice
    {
        $france = Country::where('iso_3166_2', 'FR')->firstOrFail();
        $germany = Country::where('iso_3166_2', 'DE')->firstOrFail();

        $settings = CompanySettings::defaults();
        $settings->country_id = (string) $france->id;
        $settings->currency_id = '3';
        $settings->vat_number = 'FR12345678901';
        $settings->id_number = '12345678900012';
        $settings->e_invoice_type = 'PEPPOL';
        $settings->email = $this->faker->safeEmail();

        $taxData = new TaxModel();
        $taxData->regions->EU->tax_all_subregions = true;
        $taxData->seller_subregion = 'FR';

        $this->company->settings = $settings;
        $this->company->tax_data = $taxData;
        $this->company->calculate_taxes = true;
        $this->company->save();

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => $germany->id,
            'classification' => 'business',
            'has_valid_vat_number' => false,
            'vat_number' => 'DE173755434',
            'name' => 'B2BI Buyer GmbH',
        ]);

        $contact = ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $client->company_id,
            'user_id' => $client->user_id,
            'is_primary' => true,
            'send_email' => true,
            'email' => $this->faker->safeEmail(),
        ]);

        $client->setRelation('company', $this->company);
        $client->setRelation('contacts', collect([$contact]));
        $client->setRelation('country', $germany);

        $item = InvoiceItemFactory::create();
        $item->quantity = 2;
        $item->cost = 500;
        $item->tax_name1 = 'VAT';
        $item->tax_rate1 = 20;
        $item->product_key = 'CONSULTING';
        $item->notes = 'Consulting services';

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => 'FR-F10-B2BI-001',
            'date' => '2026-09-15',
            'due_date' => '2026-10-15',
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'is_amount_discount' => true,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
            'status_id' => Invoice::STATUS_DRAFT,
            'line_items' => [$item],
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->setRelation('client', $client);
        $invoice->setRelation('company', $this->company);
        $invoice->save();

        return $invoice;
    }

    private function f10Serializer(): Serializer
    {
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        $propertyInfo = new PropertyInfoExtractor(
            [$reflectionExtractor],
            [$phpDocExtractor],
            [$reflectionExtractor, $phpDocExtractor],
        );

        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory, null, null, $propertyInfo);

        return new Serializer(
            [new DateTimeNormalizer(), $normalizer, new ArrayDenormalizer()],
            [new XmlEncoder(['xml_format_output' => true, 'remove_empty_tags' => true]), new JsonEncoder()],
        );
    }

    /**
     * @param array<string, mixed> $b2biInvoice
     * @return array<string, mixed>
     */
    private function storecoveF10Artifact(array $b2biInvoice): array
    {
        return [
            'legalEntityId' => -1,
            'document' => [
                'documentType' => 'fr_e_report',
                'frEReport' => [
                    'schemaVersion' => 1,
                    'typeCode' => 'IN',
                    'documentId' => 'FR-F10-B2BI-PEPPOL-SERIALIZER',
                    'issueDate' => '2026-10-10',
                    'issueTime' => '09:00:00',
                    'timeZone' => '+0200',
                    'transactionReport' => [
                        'period' => '2026-09-01 - 2026-09-30',
                        'b2biInvoices' => [
                            $b2biInvoice,
                        ],
                        'b2cTransactions' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $artifact
     */
    private function writeJsonArtifact(string $path, array $artifact): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }

    /**
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    private function removeEmptyValues(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->removeEmptyValues($value);
            }

            if ($array[$key] === [] || $array[$key] === '' || is_null($array[$key])) {
                unset($array[$key]);
            }
        }

        return $array;
    }
}
