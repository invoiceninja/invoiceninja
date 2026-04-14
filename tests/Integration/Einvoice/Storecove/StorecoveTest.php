<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Integration\Einvoice\Storecove;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Models\ClientContact;
use App\DataMapper\InvoiceItem;
use App\DataMapper\Tax\TaxModel;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Services\EDocument\Standards\Peppol;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Illuminate\Routing\Middleware\ThrottleRequests;

class StorecoveTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private int $routing_id = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        if (config('ninja.testvars.travis') !== false || !config('ninja.storecove_api_key')) {
            $this->markTestSkipped("do not run in CI");
        }

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

    }
    
    /**
     * setupTestData
     *
     * Setups a base company and client with data
     * prepped for a test scenario.
     * 
     * company/client metadata can be passed in as parameters.
     * @param  array $params
     * @return array
     */
    private function setupTestData(array $params = []): array
    {

        $settings = CompanySettings::defaults();
        $settings->vat_number = $params['company_vat'] ?? 'DE123456789';
        $settings->id_number = $params['company_id_number'] ?? '';
        $settings->classification = $params['company_classification'] ?? 'business';
        $settings->country_id = Country::where('iso_3166_2', $params['company_country'] ?? 'DE')->first()->id;
        $settings->email = $this->faker->safeEmail();
        $settings->currency_id = '3';

        $tax_data = new TaxModel();
        $tax_data->regions->EU->has_sales_above_threshold = $params['over_threshold'] ?? false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->seller_subregion = $params['company_country'] ?? 'DE';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new \InvoiceNinja\EInvoice\Models\Peppol\BranchType\FinancialInstitutionBranch();
        $fib->ID = "DEUTDEMMXXX"; //BIC

        $pfa = new \InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount();
        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $id->value = 'DE89370400440532013000';
        $pfa->ID = $id;
        $pfa->Name = 'PFA-NAME';

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
        $this->company->calculate_taxes = true;
        $this->company->legal_entity_id = 290868;
        $this->company->e_invoice = $stub;
        $this->company->save();
        $company = $this->company;

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => Country::where('iso_3166_2', $params['client_country'] ?? 'FR')->first()->id,
            'vat_number' => $params['client_vat'] ?? '',
            'classification' => $params['classification'] ?? 'individual',
            'has_valid_vat_number' => $params['has_valid_vat'] ?? false,
            'name' => 'Test Client',
            'is_tax_exempt' => $params['is_tax_exempt'] ?? false,
            'id_number' => $params['client_id_number'] ?? '',
        ]);

        $contact = ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $client->company_id,
            'user_id' => $client->user_id,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->safeEmail()
        ]);

        $invoice = \App\Models\Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'due_date' => now()->addDays(2)->format('Y-m-d'),
            'uses_inclusive_taxes' => false,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
        ]);

        $items = $invoice->line_items;
        foreach ($items as &$item) {
            $item->tax_name2 = '';
            $item->tax_rate2 = 0;
            $item->tax_name3 = '';
            $item->tax_rate3 = 0;
            $item->uses_inclusive_taxes = false;
        }
        unset($item);

        $invoice->line_items = array_values($items);
        $invoice = $invoice->calc()->getInvoice();

        return compact('company', 'client', 'invoice');
    }
    
    /**
     * testDEtoFRB2BReverseCharge
     *
     * Tests a scenario where a DE company sends an invoice to a FR client
     * in the B2B Reverse Charge regime.
     * 
     * The company has a valid VAT number, but the client does not.
     * The company is not over the threshold for reverse charge.
     * The client is tax exempt.
     * 
     * @return void
     */
    public function testDEtoFRB2BReverseCharge()
    {

        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_id_number' => '01234567890',
            'company_country' => 'DE',
            'company_classification' => 'business',
            'client_country' => 'FR',
            'client_vat' => 'FRAA123456789',
            'client_id_number' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => true,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];

        $line_items = $invoice->line_items;

        foreach ($line_items as &$item) {
            $item->tax_id = (string)\App\Models\Product::PRODUCT_TYPE_REVERSE_TAX;
        }
        unset($item);

        $invoice->line_items = array_values($line_items);

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(floatval(0), floatval($invoice->total_taxes));
    }

    public function testDEIToDEGNoTaxes()
    {

        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => '',
            'company_id_number' => '01234567890',
            'company_country' => 'DE',
            'company_classification' => 'individual',
            'client_country' => 'DE',
            'client_vat' => '',
            'client_id_number' => '',
            'classification' => 'government',
            'has_valid_vat' => false,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => true,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(floatval(0), floatval($invoice->total_taxes));
    }

    public function testDeNoVatNumberToDeVatNumber()
    {

        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => '',
            'company_id_number' => '01234567890',
            'company_country' => 'DE',
            'company_classification' => 'individual',
            'client_country' => 'DE',
            'client_vat' => 'DE923356489',
            'client_id_number' => '',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();

        $this->assertGreaterThan(0, $invoice->total_taxes);
    }

    public function testDeToFrClientTaxExemptSending()
    {
        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'FR',
            'client_vat' => 'FRAA123456789',
            'client_id_number' => '123456789',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();
        $company = $data['company'];
        $client = $data['client'];
        $client->save();

        $this->assertEquals('DE', $company->country()->iso_3166_2);
        $this->assertEquals('FR', $client->country->iso_3166_2);

        foreach ($invoice->line_items as $item) {
            $this->assertTrue(in_array($item->tax_id, ['1','2']));
            $this->assertEquals(0, $item->tax_rate1);
        }

        $this->assertEquals(floatval(0), floatval($invoice->total_taxes));

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $delivery = new \InvoiceNinja\EInvoice\Models\Peppol\DeliveryType\Delivery();
        $delivery->ActualDeliveryDate = new \DateTime($invoice->due_date);

        $einvoice->Delivery = [$delivery];

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;
        $invoice->e_invoice = $stub;
        nlog($invoice->e_invoice);
        $invoice->save();

        $this->sendDocument($invoice);
    }

    /**
     * PtestDeToDeClientTaxExemptSending
     *
     * Disabled for now - there is an issue with internal tax exempt client in same country
     * @return void
     */
    public function PtestDeToDeClientTaxExemptSending()
    {
        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE173755434',
          'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => true,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();
        $company = $data['company'];
        $client = $data['client'];
        $client->save();

        $this->assertEquals('DE', $company->country()->iso_3166_2);
        $this->assertEquals('DE', $client->country->iso_3166_2);

        foreach ($invoice->line_items as $item) {

            $this->assertTrue(in_array($item->tax_id, ['1','2']));
            $this->assertEquals(0, $item->tax_rate1);
        }

        $this->assertEquals(floatval(0), floatval($invoice->total_taxes));
        $this->sendDocument($invoice);
    }

    public function testDeToDeSending()
    {
        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => '',
            'classification' => 'individual',
            'has_valid_vat' => false,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();
        $company = $data['company'];
        $client = $data['client'];
        $tax_rate = $company->tax_data->regions->EU->subregions->DE->tax_rate;

        $this->assertEquals('DE', $company->country()->iso_3166_2);
        $this->assertEquals('DE', $client->country->iso_3166_2);

        foreach ($invoice->line_items as $item) {

            $this->assertTrue(in_array($item->tax_id, ['1','2']));
            $this->assertEquals($tax_rate, $item->tax_rate1);
        }

        $this->sendDocument($invoice);
    }

    public function testToSeReceiverFullPayloadUsesOrgnrAndSvefaktura()
    {
        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_id_number' => '01234567890',
            'company_country' => 'DE',
            'company_classification' => 'business',
            'client_country' => 'SE',
            'client_vat' => 'SE123456789101',
            'client_id_number' => '5567891234',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();
        $company = $data['company'];
        $client = $data['client'];

        $this->assertEquals('DE', $company->country()->iso_3166_2);
        $this->assertEquals('SE', $client->country->iso_3166_2);
        $this->assertEquals('5567891234', $client->id_number);

        $invoice->save();

        // Run the full Peppol pipeline (same as SendEDocument::handle)
        $p = new Peppol($invoice);
        $p->run();

        // Build routing identifiers (same as SendEDocument line 84)
        $identifiers = $p->gateway->mutator->setClientRoutingCode()->getStorecoveMeta();

        // Build the Storecove document (same as SendEDocument line 86)
        $storecove = new Storecove();
        $result = $storecove->build($invoice)->getResult();

        $this->assertCount(0, $result['errors'], 'Storecove build should produce no errors: ' . json_encode($result['errors']));

        // Assemble the payload exactly as SendEDocument does
        $payload = [
            'legal_entity_id' => $invoice->company->legal_entity_id,
            'idempotencyGuid' => \Illuminate\Support\Str::uuid()->toString(),
            'document' => [
                'document_type' => 'invoice',
                'invoice' => $result['document'],
            ],
            'tenant_id' => $invoice->company->company_key,
            'routing' => $identifiers['routing'],
        ];

        // Assert routing contains SE:ORGNR eIdentifier with client's id_number
        $this->assertArrayHasKey('routing', $payload);
        $this->assertArrayHasKey('eIdentifiers', $payload['routing']);

        $eIdentifiers = $payload['routing']['eIdentifiers'];
        $orgnrIdentifier = collect($eIdentifiers)->firstWhere('scheme', 'SE:ORGNR');

        $this->assertNotNull($orgnrIdentifier, 'SE:ORGNR routing identifier must be present');
        $this->assertEquals('5567891234', $orgnrIdentifier['id'], 'SE:ORGNR id should be the client id_number (org number), not the VAT number');

        // Assert Svefaktura network is enabled for Swedish receivers
        $this->assertArrayHasKey('networks', $payload['routing'], 'Svefaktura networks must be present when sending to SE receiver');
        $svefaktura = collect($payload['routing']['networks'])->firstWhere('application', 'svefaktura');
        $this->assertNotNull($svefaktura, 'Svefaktura network entry must be present');
        $this->assertTrue($svefaktura['settings']['enabled'], 'Svefaktura network must be enabled');

        // Assert the document payload is well-formed
        $this->assertArrayHasKey('document', $payload);
        $this->assertEquals('invoice', $payload['document']['document_type']);
        $this->assertNotEmpty($payload['document']['invoice']);
        $this->assertEquals(290868, $payload['legal_entity_id']);
    }

    private function sendDocument($model)
    {
        $storecove = new Storecove();
        $p = new Peppol($model);
        $p->run();

        try {
            $processor = new \Saxon\SaxonProcessor();
        } catch (\Throwable $e) {
            $this->markTestSkipped('saxon not installed');
        }

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($p->toXml());
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($p->toXml());
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

        $identifiers = $p->gateway->mutator->setClientRoutingCode()->getStorecoveMeta();

        $result = $storecove->build($model)->getResult();

        if (count($result['errors']) > 0) {
            nlog("errors!");
            nlog($result);
            return $result['errors'];
        }

        $payload = [
            'legal_entity_id' => $model->company->legal_entity_id,
            "idempotencyGuid" => \Illuminate\Support\Str::uuid(),
            'document' => [
                'document_type' => 'invoice',
                'invoice' => $result['document'],
            ],
            'tenant_id' => $model->company->company_key,
            'routing' => $identifiers['routing'],
        ];
        /** Concrete implementation current linked to Storecove only */

        //@testing only
        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $r = $sc->sendJsonDocument($payload);

        nlog($r);
    }

    public function testTransformPeppolToStorecove()
    {

        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        // list of PropertyListExtractorInterface (any iterable)
        $typeExtractors = [$reflectionExtractor,$phpDocExtractor];
        // list of PropertyDescriptionExtractorInterface (any iterable)
        $descriptionExtractors = [$phpDocExtractor];
        // list of PropertyAccessExtractorInterface (any iterable)
        $propertyInitializableExtractors = [$reflectionExtractor];
        $propertyInfo = new PropertyInfoExtractor(
            $propertyInitializableExtractors,
            $descriptionExtractors,
            $typeExtractors,
        );
        $xml_encoder = new XmlEncoder(['xml_format_output' => true, 'remove_empty_tags' => true,]);
        $json_encoder = new JsonEncoder();

        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $metadataAwareNameConverter = new MetadataAwareNameConverter($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());

        $normalizer = new ObjectNormalizer($classMetadataFactory, $metadataAwareNameConverter, null, $propertyInfo);

        $normalizers = [new DateTimeNormalizer(), $normalizer,  new ArrayDenormalizer()];
        $encoders = [$xml_encoder, $json_encoder];
        $serializer = new Serializer($normalizers, $encoders);

        $context = [
          DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
          AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ];

        $p = file_get_contents(base_path('tests/Integration/Einvoice/samples/peppol.xml'));

        $e = new \InvoiceNinja\EInvoice\EInvoice();
        $peppolInvoice = $e->decode('Peppol', $p, 'xml');

        $this->assertInstanceOf(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $peppolInvoice);

        $parent = \App\Services\EDocument\Gateway\Storecove\Models\Invoice::class;

        $peppolInvoice = $data = $e->encode($peppolInvoice, 'json', $context);

        $invoice = $serializer->deserialize($peppolInvoice, $parent, 'json', $context);

        $this->assertInstanceOf($parent, $invoice);

        $s_invoice = $serializer->encode($invoice, 'json', $context);

        $arr = json_decode($s_invoice, true);

        $arr = $this->removeEmptyValues($arr);

        // nlog($arr);

    }


    private function removeEmptyValues(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->removeEmptyValues($value);
                if (empty($array[$key])) {
                    unset($array[$key]);
                }
            } elseif ($value === null || $value === '') {
                unset($array[$key]);
            }
        }
        // nlog($array);
        return $array;
    }


    public function testNormalizingToStorecove()
    {

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $invoice = $this->createATData();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach ($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;

        $p = new Peppol($invoice);

        $this->assertIsString($p->run()->toXml());


    }

    public function testStorecoveTransformerWithPercentageDiscount()
    {

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $invoice = $this->createATData();
        $invoice->is_amount_discount = false;

        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->is_amount_discount = false;
        $item->discount = 5;
        $item->tax_rate1 = 20;
        $item->tax_name1 = 'VAT';

        $invoice->line_items = [$item];
        $invoice->calc()->getInvoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach ($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;

        $p = new Peppol($invoice);
        $p->run();
        $peppolInvoice = $p->getDocument();

        $this->assertNotNull($peppolInvoice);
    }



    public function testUnsetOfVatNumers()
    {

        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->regions->US->tax_all_subregions = true;
        $tax_data->regions->US->has_sales_above_threshold = true;

        $tax_data->regions->EU->subregions->DE->vat_number = 'DE12345';
        $tax_data->regions->EU->subregions->RO->vat_number = 'RO12345';

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
        ]);


        $this->assertEquals('DE12345', $company->tax_data->regions->EU->subregions->DE->vat_number);
        $this->assertEquals('RO12345', $company->tax_data->regions->EU->subregions->RO->vat_number);

        $this->assertEquals('DE12345', $company->tax_data->regions->EU->subregions->DE->vat_number);
        $this->assertEquals('RO12345', $company->tax_data->regions->EU->subregions->RO->vat_number);

        $company->tax_data = $this->unsetVatNumbers($company->tax_data);

        $company->save();

        $company = $company->fresh();

        $this->assertFalse(property_exists($company->tax_data->regions->EU->subregions->DE, 'vat_number'), "DE subregion should not have vat_number property");
        $this->assertFalse(property_exists($company->tax_data->regions->EU->subregions->RO, 'vat_number'), "RO subregion should not have vat_number property");

    }


    private function unsetVatNumbers(mixed $taxData): mixed
    {
        if (isset($taxData->regions->EU->subregions)) {
            foreach ($taxData->regions->EU->subregions as $country => $data) {
                if (isset($data->vat_number)) {
                    $newData = new \stdClass();
                    if (is_object($data)) {
                        $dataArray = get_object_vars($data);
                        foreach ($dataArray as $key => $value) {
                            if ($key !== 'vat_number') {
                                $newData->$key = $value;
                            }
                        }
                    }
                    $taxData->regions->EU->subregions->$country = $newData;
                }
            }
        }

        return $taxData;
    }
    // public function testCreateLegalEntity()
    // {12/345/67890

    // $data = [
    //     'acts_as_receiver' => true,
    //     'acts_as_sender' => true,
    //     'advertisements' => ['invoice'],
    //     'city' => $this->company->settings->city,
    //     'country' => 'DE',
    //     'county' => $this->company->settings->state,
    //     'line1' => $this->company->settings->address1,
    //     'line2' => $this->company->settings->address2,
    //     'party_name' => $this->company->present()->name(),
    //     'tax_registered' => true,
    //     'tenant_id' => $this->company->company_key,
    //     'zip' => $this->company->settings->postal_code,
    //     'peppol_identifiers' => [
    //         'scheme' => 'DE:STNR',
    //         'id' => 'DE:VAT'
    //     ],
    // ];

    // $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
    // $r = $sc->createLegalEntity($data);

    //     $this->assertIsArray($r);

    // }

    // public function testAddPeppolIdentifier()
    // {

    //         $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
    //         $r = $sc->addIdentifier(291394, "DE923356489", "DE:VAT");

    //         nlog($r);

    // }

    // public function testUpdateLegalEntity()
    // {
    //     $data = [
    //         'peppol_identifiers' => [
    //             'scheme' => 'DE:VAT',
    //             'id' => 'DE:VAT'
    //         ],
    //     ];

    //     $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
    //     $r = $sc->updateLegalEntity(290868, $data);

    //     $this->assertIsArray($r);
    //     nlog($r);

    // }
    /*
        public function testGetLegalEntity()
        {

            $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
            $r = $sc->getLegalEntity(290868);

            $this->assertIsArray($r);

        }

        public function testSendDocument()
        {

            $x = '
            <?xml version="1.0" encoding="utf-8"?>
            <Invoice
                xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
      <cbc:ID>DE-77323</cbc:ID>
      <cbc:IssueDate>2024-07-18</cbc:IssueDate>
      <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
      <cac:AccountingSupplierParty>
        <cac:Party>
          <cac:PartyName>
            <cbc:Name>Untitled Company</cbc:Name>
          </cac:PartyName>
          <cac:PostalAddress>
            <cbc:StreetName>Dudweilerstr. 34b</cbc:StreetName>
            <cbc:CityName>Ost Alessa</cbc:CityName>
            <cbc:PostalZone>98060</cbc:PostalZone>
            <cbc:CountrySubentity>Bayern</cbc:CountrySubentity>
            <cac:Country>
              <cbc:IdentificationCode>DE</cbc:IdentificationCode>
            </cac:Country>
          </cac:PostalAddress>
          <cac:PhysicalLocation>
            <cbc:StreetName>Dudweilerstr. 34b</cbc:StreetName>
            <cbc:CityName>Ost Alessa</cbc:CityName>
            <cbc:PostalZone>98060</cbc:PostalZone>
            <cbc:CountrySubentity>Bayern</cbc:CountrySubentity>
            <cac:Country>
              <cbc:IdentificationCode>DE</cbc:IdentificationCode>
            </cac:Country>
          </cac:PhysicalLocation>
          <cac:Contact>
            <cbc:ElectronicMail>owner@gmail.com</cbc:ElectronicMail>
          </cac:Contact>
        </cac:Party>
      </cac:AccountingSupplierParty>
      <cac:AccountingCustomerParty>
        <cac:Party>
          <cac:PartyName>
            <cbc:Name>German Client Name</cbc:Name>
          </cac:PartyName>
          <cac:PostalAddress>
            <cbc:StreetName>Kinderhausen 96b</cbc:StreetName>
            <cbc:CityName>S&#xFC;d Jessestadt</cbc:CityName>
            <cbc:PostalZone>33323</cbc:PostalZone>
            <cbc:CountrySubentity>Bayern</cbc:CountrySubentity>
            <cac:Country>
              <cbc:IdentificationCode>DE</cbc:IdentificationCode>
            </cac:Country>
          </cac:PostalAddress>
          <cac:PhysicalLocation>
            <cbc:StreetName>Kinderhausen 96b</cbc:StreetName>
            <cbc:CityName>S&#xFC;d Jessestadt</cbc:CityName>
            <cbc:PostalZone>33323</cbc:PostalZone>
            <cbc:CountrySubentity>Bayern</cbc:CountrySubentity>
            <cac:Country>
              <cbc:IdentificationCode>DE</cbc:IdentificationCode>
            </cac:Country>
          </cac:PhysicalLocation>
          <cac:Contact>
            <cbc:ElectronicMail>No Email Set</cbc:ElectronicMail>
          </cac:Contact>
        </cac:Party>
      </cac:AccountingCustomerParty>
      <cac:PaymentMeans>
        <PayeeFinancialAccount>
          <ID>DE89370400440532013000</ID>
          <Name>PFA-NAME</Name>
          <AliasName>PFA-Alias</AliasName>
          <AccountTypeCode>CHECKING</AccountTypeCode>
          <AccountFormatCode>IBAN</AccountFormatCode>
          <CurrencyCode>EUR</CurrencyCode>
          <FinancialInstitutionBranch>
            <ID>DEUTDEMMXXX</ID>
            <Name>Deutsche Bank</Name>
          </FinancialInstitutionBranch>
        </PayeeFinancialAccount>
      </cac:PaymentMeans>
      <cac:TaxTotal/>
      <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="EUR">100</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="EUR">100</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="EUR">119.00</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="EUR">119.00</cbc:PayableAmount>
      </cac:LegalMonetaryTotal>
      <cac:InvoiceLine>
        <cbc:ID>1</cbc:ID>
        <cbc:InvoicedQuantity>10</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="EUR">100</cbc:LineExtensionAmount>
        <cac:TaxTotal>
          <cbc:TaxAmount currencyID="EUR">19</cbc:TaxAmount>
          <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="EUR">100</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="EUR">19</cbc:TaxAmount>
            <cac:TaxCategory>
              <cbc:ID>C62</cbc:ID>
              <cbc:Percent>19</cbc:Percent>
              <cac:TaxScheme>
                <cbc:ID>mwst</cbc:ID>
              </cac:TaxScheme>
            </cac:TaxCategory>
          </cac:TaxSubtotal>
        </cac:TaxTotal>
        <cac:Item>
          <cbc:Description>Product Description</cbc:Description>
          <cbc:Name>Product Key</cbc:Name>
        </cac:Item>
        <cac:Price>
          <cbc:PriceAmount currencyID="EUR">10</cbc:PriceAmount>
        </cac:Price>
      </cac:InvoiceLine>
      ';

    //inclusive
    $x = '<?xml version="1.0" encoding="utf-8"?>
    <Invoice
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xmlns:xsd="http://www.w3.org/2001/XMLSchema"
        xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
    <cbc:ID>DE-93090</cbc:ID>
      <cbc:IssueDate>2024-07-18</cbc:IssueDate>
      <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
      <cac:AccountingSupplierParty>
        <cac:Party>
          <cac:PartyName>
            <cbc:Name>Untitled Company</cbc:Name>
          </cac:PartyName>
          <cac:PostalAddress>
            <cbc:StreetName>Dudweilerstr. 34b</cbc:StreetName>
            <cbc:CityName>Ost Alessa</cbc:CityName>
            <cbc:PostalZone>98060</cbc:PostalZone>
            <cbc:CountrySubentity>Bayern</cbc:CountrySubentity>
            <cac:Country>
              <cbc:IdentificationCode>DE</cbc:IdentificationCode>
            </cac:Country>
          </cac:PostalAddress>
          <cac:PhysicalLocation>
            <cbc:StreetName>Dudweilerstr. 34b</cbc:StreetName>
            <cbc:CityName>Ost Alessa</cbc:CityName>
            <cbc:PostalZone>98060</cbc:PostalZone>
            <cbc:CountrySubentity>Bayern</cbc:CountrySubentity>
            <cac:Country>
              <cbc:IdentificationCode>DE</cbc:IdentificationCode>
            </cac:Country>
          </cac:PhysicalLocation>
          <cac:Contact>
            <cbc:ElectronicMail>owner@gmail.com</cbc:ElectronicMail>
          </cac:Contact>
        </cac:Party>
      </cac:AccountingSupplierParty>
      <cac:AccountingCustomerParty>
        <cac:Party>
          <cac:PartyName>
            <cbc:Name>German Client Name</cbc:Name>
          </cac:PartyName>
          <cac:PostalAddress>
            <cbc:StreetName>Kinderhausen 96b</cbc:StreetName>
            <cbc:CityName>S&#xFC;d Jessestadt</cbc:CityName>
            <cbc:PostalZone>33323</cbc:PostalZone>
            <cbc:CountrySubentity>Bayern</cbc:CountrySubentity>
            <cac:Country>
              <cbc:IdentificationCode>DE</cbc:IdentificationCode>
            </cac:Country>
          </cac:PostalAddress>
          <cac:PhysicalLocation>
            <cbc:StreetName>Kinderhausen 96b</cbc:StreetName>
            <cbc:CityName>S&#xFC;d Jessestadt</cbc:CityName>
            <cbc:PostalZone>33323</cbc:PostalZone>
            <cbc:CountrySubentity>Bayern</cbc:CountrySubentity>
            <cac:Country>
              <cbc:IdentificationCode>DE</cbc:IdentificationCode>
            </cac:Country>
          </cac:PhysicalLocation>
          <cac:Contact>
            <cbc:ElectronicMail>No Email Set</cbc:ElectronicMail>
          </cac:Contact>
        </cac:Party>
      </cac:AccountingCustomerParty>
      <cac:PaymentMeans>
        <PayeeFinancialAccount>
          <ID>DE89370400440532013000</ID>
          <Name>PFA-NAME</Name>
          <AliasName>PFA-Alias</AliasName>
          <AccountTypeCode>CHECKING</AccountTypeCode>
          <AccountFormatCode>IBAN</AccountFormatCode>
          <CurrencyCode>EUR</CurrencyCode>
          <FinancialInstitutionBranch>
            <ID>DEUTDEMMXXX</ID>
            <Name>Deutsche Bank</Name>
          </FinancialInstitutionBranch>
        </PayeeFinancialAccount>
      </cac:PaymentMeans>
      <cac:TaxTotal>
        <cbc:TaxAmount currencyID="EUR">15.97</cbc:TaxAmount>
        <cac:TaxSubtotal>
          <cbc:TaxableAmount currencyID="EUR">84.03</cbc:TaxableAmount>
          <cbc:TaxAmount currencyID="EUR">15.97</cbc:TaxAmount>
          <cac:TaxCategory>
            <cbc:ID>C62</cbc:ID>
            <cbc:Percent>0</cbc:Percent>
            <cac:TaxScheme>
              <cbc:ID></cbc:ID>
            </cac:TaxScheme>
          </cac:TaxCategory>
        </cac:TaxSubtotal>
      </cac:TaxTotal>
      <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="EUR">84.03</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="EUR">84.03</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="EUR">100.00</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="EUR">100.00</cbc:PayableAmount>
      </cac:LegalMonetaryTotal>
      <cac:InvoiceLine>
        <cbc:ID>1</cbc:ID>
        <cbc:InvoicedQuantity>10</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="EUR">84.03</cbc:LineExtensionAmount>
        <cac:TaxTotal>
          <cbc:TaxAmount currencyID="EUR">15.97</cbc:TaxAmount>
          <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="EUR">84.03</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="EUR">15.97</cbc:TaxAmount>
            <cac:TaxCategory>
              <cbc:ID>C62</cbc:ID>
              <cbc:Percent>19</cbc:Percent>
              <cac:TaxScheme>
                <cbc:ID>mwst</cbc:ID>
              </cac:TaxScheme>
            </cac:TaxCategory>
          </cac:TaxSubtotal>
        </cac:TaxTotal>
        <cac:Item>
          <cbc:Description>Product Description</cbc:Description>
          <cbc:Name>Product Key</cbc:Name>
        </cac:Item>
        <cac:Price>
          <cbc:PriceAmount currencyID="EUR">8.403</cbc:PriceAmount>
        </cac:Price>
      </cac:InvoiceLine>
    ';


            $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
            $sc->sendDocument($x, 290868);

        }
    */
    public function testCreateTestData()
    {
        $this->createESData();
        $this->createATData();
        $this->createDEData();
        $this->createFRData();
        $this->createITData();
        $this->createROData();

        $this->assertTrue(true);
    }

    public function testCreateCHClient()
    {

        Client::unguard();

        $c =
        Client::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Test Company AG',
            'website' => 'https://www.testcompany.ch',
            'private_notes' => 'These are some private notes about the test client.',
            'balance' => 0,
            'paid_to_date' => 0,
            'vat_number' => '654321987',
            'id_number' => 'CH9300762011623852957', // Sample Swiss IBAN
            'custom_value1' => '2024-07-22 10:00:00',
            'custom_value2' => 'blue',
            'custom_value3' => 'sampleword',
            'custom_value4' => 'test@example.com',
            'address1' => '123',
            'address2' => 'Test Street 45',
            'city' => 'Zurich',
            'state' => 'Zurich',
            'postal_code' => '8001',
            'country_id' => '756', // Switzerland
            'shipping_address1' => '123',
            'shipping_address2' => 'Test Street 45',
            'shipping_city' => 'Zurich',
            'shipping_state' => 'Zurich',
            'shipping_postal_code' => '8001',
            'shipping_country_id' => '756', // Switzerland
            'settings' => ClientSettings::Defaults(),
            'client_hash' => \Illuminate\Support\Str::random(32),
            'routing_id' => '',
        ]);


        $this->assertInstanceOf(\App\Models\Client::class, $c);

    }


    private function createITData($business = true)
    {

        $this->routing_id = 294636;

        $settings = CompanySettings::defaults();
        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.it';
        $settings->address1 = 'Via del Corso, 28';
        $settings->address2 = 'Palazzo delle Telecomunicazioni';
        $settings->city = 'Roma';
        $settings->state = 'Lazio';
        $settings->postal_code = '00187';
        $settings->phone = '06 1234567';
        $settings->email = \Illuminate\Support\Str::random(32)."@example.com";
        $settings->country_id = '380'; // Italy's ISO country code
        $settings->vat_number = 'IT92443356490'; // Italian VAT number
        $settings->id_number = 'RM 123456'; // Typical Italian company registration format
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1'; // CET (Central European Time)
        $settings->entity_send_time = 0;
        $settings->e_invoice_type = 'PEPPOL';
        $settings->currency_id = '3'; // Euro (EUR)
        $settings->classification = 'business';


        $company = Company::factory()->create([
          'account_id' => $this->account->id,
          'settings' => $settings,
        ]);

        $this->user->companies()->attach($company->id, [
            'account_id' => $this->account->id,
            'is_owner' => true,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'notifications' => CompanySettings::notificationAdminDefaults(),
            'settings' => null,
        ]);

        Client::unguard();

        $c =
        Client::create([
          'company_id' => $company->id,
          'user_id' => $this->user->id,
          'name' => 'Impresa Esempio S.p.A.',
          'website' => 'https://www.impresa-esempio.it',
          'private_notes' => 'Queste sono note private per il cliente di prova.',
          'balance' => 0,
          'paid_to_date' => 0,
          'vat_number' => 'IT92443356489', // Italian VAT number with IT prefix
          'id_number' => 'B12345678', // Typical format for Italian company registration numbers
          'custom_value1' => '2024-07-22 10:00:00',
          'custom_value2' => 'blu', // Italian for blue
          'custom_value3' => 'parolaesempio', // Italian for sample word
          'custom_value4' => 'test@esempio.it',
          'address1' => 'Via Esempio 123',
          'address2' => '2º Piano, Ufficio 45',
          'city' => 'Roma',
          'state' => 'Lazio',
          'postal_code' => '00187',
          'country_id' => '380', // Italy
          'shipping_address1' => 'Via Esempio 123',
          'shipping_address2' => '2º Piano, Ufficio 45',
          'shipping_city' => 'Roma',
          'shipping_state' => 'Lazio',
          'shipping_postal_code' => '00187',
          'shipping_country_id' => '380', // Italy
          'settings' => ClientSettings::defaults(),
          'client_hash' => \Illuminate\Support\Str::random(32),
          'routing_id' => 'SCSCSCS',
          'classification' => 'business',
        ]);

        ClientContact::factory()->create([
          'company_id' => $company->id,
          'user_id' => $this->user->id,
          'client_id' => $c->id,
          'first_name' => 'Contact First',
          'last_name' => 'Contact Last',
          'email' => 'david+c1@invoiceninja.com',
        ]);

        $c2 =
          Client::create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'Impresa Esempio S.p.A.',
            'website' => 'https://www.impresa-esempio.it',
            'private_notes' => 'Queste sono note private per il cliente di prova.',
            'balance' => 0,
            'paid_to_date' => 0,
            'vat_number' => 'RSSMRA85M01H501Z', // Italian VAT number with IT prefix
            'id_number' => 'B12345678', // Typical format for Italian company registration numbers
            'custom_value1' => '2024-07-22 10:00:00',
            'custom_value2' => 'blu', // Italian for blue
            'custom_value3' => 'parolaesempio', // Italian for sample word
            'custom_value4' => 'test@esempio.it',
            'address1' => 'Via Esempio 123',
            'address2' => '2º Piano, Ufficio 45',
            'city' => 'Roma',
            'state' => 'Lazio',
            'postal_code' => '00187',
            'country_id' => '380', // Italy
            'shipping_address1' => 'Via Esempio 123',
            'shipping_address2' => '2º Piano, Ufficio 45',
            'shipping_city' => 'Roma',
            'shipping_state' => 'Lazio',
            'shipping_postal_code' => '00187',
            'shipping_country_id' => '380', // Italy
            'settings' => ClientSettings::defaults(),
            'client_hash' => \Illuminate\Support\Str::random(32),
            'routing_id' => 'SCSCSCS',
            'classification' => 'individual',
          ]);


        ClientContact::factory()->create([
          'company_id' => $company->id,
          'user_id' => $this->user->id,
          'client_id' => $c2->id,
          'first_name' => 'Contact First',
          'last_name' => 'Contact Last',
          'email' => 'david+c2@invoiceninja.com',
        ]);


        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->tax_rate1 = 22;
        $item->tax_name1 = 'IVA';

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $business ? $c->id : $c2->id,
            'discount' => 0,
            'uses_inclusive_taxes' => false,
            'status_id' => 1,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name2' => '',
            'tax_name3' => '',
            'line_items' => [$item],
            'number' => 'IT-'.rand(1000, 100000),
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        return $invoice;

    }

    private function createDEData()
    {
        // $this->routing_id = 293098;

        $settings = CompanySettings::defaults();

        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.de';
        $settings->address1 = 'Musterstraße 12';
        $settings->address2 = 'Gebäude B';
        $settings->city = 'Berlin';
        $settings->state = 'Berlin';
        $settings->postal_code = '10115';
        $settings->phone = '030 1234567';
        $settings->email = \Illuminate\Support\Str::random(32)."@example.com";
        $settings->country_id = '276'; // Germany's ISO country code
        $settings->vat_number = 'DE123456789';
        $settings->id_number = 'HRB 98765';
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1'; // CET (Central European Time)
        $settings->entity_send_time = 0;
        $settings->e_invoice_type = 'PEPPOL';
        $settings->currency_id = '3'; // Euro
        $settings->classification = 'business';


        $company = Company::factory()->create([
          'account_id' => $this->account->id,
          'settings' => $settings,
        ]);

        $this->user->companies()->attach($company->id, [
            'account_id' => $this->account->id,
            'is_owner' => true,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'notifications' => CompanySettings::notificationAdminDefaults(),
            'settings' => null,
        ]);

        Client::unguard();

        $c =
        Client::create([
        'company_id' => $company->id,
        'user_id' => $this->user->id,
        'name' => 'Beispiel GmbH',
        'website' => 'https://www.beispiel.de',
        'private_notes' => 'Dies sind private Notizen für den Testkunden.',
        'balance' => 0,
        'paid_to_date' => 0,
        'vat_number' => 'DE123456789', // German VAT number with DE prefix
        'id_number' => 'HRB 12345', // Typical format for German company registration numbers
        'custom_value1' => '2024-07-22 10:00:00',
        'custom_value2' => 'blau', // German for blue
        'custom_value3' => 'beispielwort', // German for sample word
        'custom_value4' => 'test@beispiel.de',
        'address1' => 'Beispielstraße 123',
        'address2' => '2. Stock, Büro 45',
        'city' => 'Berlin',
        'state' => 'Berlin',
        'postal_code' => '10115',
        'country_id' => '276', // Germany
        'shipping_address1' => 'Beispielstraße 123',
        'shipping_address2' => '2. Stock, Büro 45',
        'shipping_city' => 'Berlin',
        'shipping_state' => 'Berlin',
        'shipping_postal_code' => '10115',
        'shipping_country_id' => '276', // Germany
        'settings' => ClientSettings::Defaults(),
        'client_hash' => \Illuminate\Support\Str::random(32),
        'routing_id' => 'DEDEDE',
        ]);

        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->tax_rate1 = 19;
        $item->tax_name1 = 'mwst';

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $c->id,
            'discount' => 0,
            'uses_inclusive_taxes' => false,
            'status_id' => 1,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name2' => '',
            'tax_name3' => '',
            'line_items' => [$item],
            'number' => 'DE-'.rand(1000, 100000),
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        return $invoice;

    }

    private function createESData()
    {
        $this->routing_id = 293098;

        $settings = CompanySettings::defaults();
        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.de';
        $settings->address1 = 'Calle Gran Vía, 28';
        $settings->address2 = 'Edificio Telefónica';
        $settings->city = 'Madrid';
        $settings->state = 'Madrid';
        $settings->postal_code = '28013';
        $settings->phone = '030 1234567';
        $settings->email = \Illuminate\Support\Str::random(32)."@example.com";
        $settings->country_id = '724'; // Germany's ISO country code
        $settings->vat_number = 'ESB16645678';
        $settings->id_number = 'HRB 12345';
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1'; // CET (Central European Time)
        $settings->entity_send_time = 0;
        $settings->e_invoice_type = 'PEPPOL';
        $settings->currency_id = '3';
        $settings->classification = 'business';

        $company = Company::factory()->create([
          'account_id' => $this->account->id,
          'settings' => $settings,
        ]);

        $this->user->companies()->attach($company->id, [
            'account_id' => $this->account->id,
            'is_owner' => true,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'notifications' => CompanySettings::notificationAdminDefaults(),
            'settings' => null,
        ]);

        Client::unguard();

        $c =
        Client::create([
          'company_id' => $company->id,
          'user_id' => $this->user->id,
          'name' => 'Empresa Ejemplo S.A.',
          'website' => 'https://www.empresa-ejemplo.es',
          'private_notes' => 'Estas son notas privadas para el cliente de prueba.',
          'balance' => 0,
          'paid_to_date' => 0,
          'vat_number' => 'ESB12345678', // Spanish VAT number with ES prefix
          'id_number' => 'B12345678', // Typical format for Spanish company registration numbers
          'custom_value1' => '2024-07-22 10:00:00',
          'custom_value2' => 'azul', // Spanish for blue
          'custom_value3' => 'palabraejemplo', // Spanish for sample word
          'custom_value4' => 'test@ejemplo.com',
          'address1' => 'Calle Ejemplo 123',
          'address2' => '2ª Planta, Oficina 45',
          'city' => 'Madrid',
          'state' => 'Madrid',
          'postal_code' => '28013',
          'country_id' => '724', // Spain
          'shipping_address1' => 'Calle Ejemplo 123',
          'shipping_address2' => '2ª Planta, Oficina 45',
          'shipping_city' => 'Madrid',
          'shipping_state' => 'Madrid',
          'shipping_postal_code' => '28013',
          'shipping_country_id' => '724', // Spain
          'settings' => ClientSettings::Defaults(),
          'client_hash' => \Illuminate\Support\Str::random(32),
          'routing_id' => 'SCSCSC',
        ]);

        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->tax_rate1 = 21;
        $item->tax_name1 = 'IVA';

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $c->id,
            'discount' => 0,
            'uses_inclusive_taxes' => false,
            'status_id' => 1,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name2' => '',
            'tax_name3' => '',
            'line_items' => [$item],
            'number' => 'ES-'.rand(1000, 100000),
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        return $invoice;

    }

    private function createFRData()
    {
        $this->routing_id = 293338;

        $settings = CompanySettings::defaults();
        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.de';

        $settings->address1 = '10 Rue de la Paix';
        $settings->address2 = 'Bâtiment A, Bureau 5';
        $settings->city = 'Paris';
        $settings->state = 'Île-de-France';
        $settings->postal_code = '75002';
        $settings->phone = '01 23456789';
        $settings->email = \Illuminate\Support\Str::random(32)."@example.com";
        $settings->country_id = '250'; // France's ISO country code
        $settings->vat_number = 'FR82345678911';
        $settings->id_number = '12345678900010';
        $settings->classification = 'business';
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1'; // CET (Central European Time)
        $settings->entity_send_time = 0;
        $settings->e_invoice_type = 'PEPPOL';
        $settings->currency_id = '3';

        $company = Company::factory()->create([
          'account_id' => $this->account->id,
          'settings' => $settings,
        ]);

        $this->user->companies()->attach($company->id, [
            'account_id' => $this->account->id,
            'is_owner' => true,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'notifications' => CompanySettings::notificationAdminDefaults(),
            'settings' => null,
        ]);

        Client::unguard();

        $c =
        Client::create([
          'company_id' => $company->id,
          'user_id' => $this->user->id,
          'name' => 'Exemple Société S.A.',
          'website' => 'https://www.exemple-societe.fr',
          'private_notes' => 'Ceci est une note privée pour le client test.',
          'balance' => 0,
          'paid_to_date' => 0,
          'vat_number' => 'FR12345678901',
          'id_number' => '12345678900010', // Typical format for French company registration numbers
          'custom_value1' => '2024-07-22 10:00:00',
          'custom_value2' => 'bleu',
          'custom_value3' => 'motexemple',
          'custom_value4' => 'test@example.com',
          'address1' => '123 Rue de l\'Exemple',
          'address2' => '2ème étage, Bureau 45',
          'city' => 'Paris',
          'state' => 'Île-de-France',
          'postal_code' => '75001',
          'country_id' => '250', // France
          'shipping_address1' => '123 Rue de l\'Exemple',
          'shipping_address2' => '2ème étage, Bureau 45',
          'shipping_city' => 'Paris',
          'shipping_state' => 'Île-de-France',
          'shipping_postal_code' => '75001',
          'shipping_country_id' => '250', // France
          'classification' => 'business',
          'settings' => ClientSettings::Defaults(),
          'client_hash' => \Illuminate\Support\Str::random(32),
          'routing_id' => '',
        ]);


        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->tax_rate1 = 20;
        $item->tax_name1 = 'VAT';

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $c->id,
            'discount' => 0,
            'uses_inclusive_taxes' => false,
            'status_id' => 1,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name2' => '',
            'tax_name3' => '',
            'line_items' => [$item],
            'number' => 'DE-'.rand(1000, 100000),
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();


        return $invoice;

    }

    private function createATData(bool $is_gov = false)
    {

        $this->routing_id = 293801;

        $settings = CompanySettings::defaults();
        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.at';
        $settings->address1 = 'Musterstraße 1';
        $settings->address2 = 'Stockwerk 2, Büro 3';
        $settings->city = 'Vienna';
        $settings->state = 'Vienna';
        $settings->postal_code = '1010';
        $settings->phone = '+43 1 23456789';
        $settings->email = \Illuminate\Support\Str::random(32)."@example.com";
        $settings->country_id = '40'; // Austria's ISO country code
        $settings->vat_number = 'ATU92335648';
        $settings->id_number = 'FN 123456x';
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1'; // CET (Central European Time)
        $settings->entity_send_time = 0;
        $settings->e_invoice_type = 'PEPPOL';
        $settings->currency_id = '3';


        $company = Company::factory()->create([
          'account_id' => $this->account->id,
          'settings' => $settings,
        ]);

        $this->user->companies()->attach($company->id, [
            'account_id' => $this->account->id,
            'is_owner' => true,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'notifications' => CompanySettings::notificationAdminDefaults(),
            'settings' => null,
        ]);

        Client::unguard();

        $c =
        Client::create([
          'company_id' => $company->id,
          'user_id' => $this->user->id,
          'name' => 'Beispiel Firma GmbH',
          'website' => 'https://www.beispiel-firma.at',
          'private_notes' => 'Dies sind private Notizen zum Testkunden.',
          'balance' => 0,
          'paid_to_date' => 0,
          'vat_number' => 'ATU87654321',
          'id_number' => $is_gov ? 'ATU12312321' : 'FN 123456x', // Example format for Austrian company registration numbers
          'custom_value1' => '2024-07-22 10:00:00',
          'custom_value2' => 'blau',
          'custom_value3' => 'musterwort',
          'custom_value4' => 'test@example.com',
          'address1' => 'Musterstraße 123',
          'address2' => '2. Etage, Büro 45',
          'city' => 'Vienna',
          'state' => 'Vienna',
          'postal_code' => '1010',
          'country_id' => '40', // Austria
          'shipping_address1' => 'Musterstraße 123',
          'shipping_address2' => '2. Etage, Büro 45',
          'shipping_city' => 'Vienna',
          'shipping_state' => 'Vienna',
          'shipping_postal_code' => '1010',
          'shipping_country_id' => '40', // Austria
          'settings' => ClientSettings::Defaults(),
          'client_hash' => \Illuminate\Support\Str::random(32),
          'routing_id' => '',
          'classification' => $is_gov ? 'government' : 'business',
        ]);


        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->tax_rate1 = 20;
        $item->tax_name1 = 'VAT';

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $c->id,
            'discount' => 0,
            'uses_inclusive_taxes' => false,
            'status_id' => 1,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name2' => '',
            'tax_name3' => '',
            'line_items' => [$item],
            'number' => 'DE-'.rand(1000, 100000),
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        return $invoice;

    }

    private function createROData()
    {
        $this->routing_id = 294639;

        $settings = CompanySettings::defaults();
        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.ro';
        $settings->address1 = 'Strada Exemplu, 28';
        $settings->address2 = 'Clădirea Exemplu';
        $settings->city = 'Bucharest';
        $settings->state = 'Bucharest';
        $settings->postal_code = '010101';
        $settings->phone = '021 1234567';
        $settings->email = \Illuminate\Support\Str::random(32)."@example.com";
        $settings->country_id = '642'; // Romania's ISO country code
        $settings->vat_number = 'RO92443356490'; // Romanian VAT number format
        $settings->id_number = 'B12345678'; // Typical Romanian company registration format
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1'; // CET (Central European Time)
        $settings->entity_send_time = 0;
        $settings->e_invoice_type = 'PEPPOL';
        $settings->currency_id = '3'; // Euro (EUR)
        $settings->classification = 'business';


        $company = Company::factory()->create([
          'account_id' => $this->account->id,
          'settings' => $settings,
        ]);

        $this->user->companies()->attach($company->id, [
            'account_id' => $this->account->id,
            'is_owner' => true,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'notifications' => CompanySettings::notificationAdminDefaults(),
            'settings' => null,
        ]);

        Client::unguard();

        $c =
        Client::create([
          'company_id' => $company->id,
          'user_id' => $this->user->id,
          'name' => 'Impresa Esempio S.R.L.',
          'website' => 'https://www.impresa-esempio.ro',
          'private_notes' => 'Acestea sunt note private pentru clientul de test.',
          'balance' => 0,
          'paid_to_date' => 0,
          'vat_number' => 'RO9244336489', // Romanian VAT number with RO prefix
          'id_number' => 'J40/12345/2024', // Typical format for Romanian company registration numbers
          'custom_value1' => '2024-07-22 10:00:00',
          'custom_value2' => 'albastru', // Romanian for blue
          'custom_value3' => 'cuvantexemplu', // Romanian for sample word
          'custom_value4' => 'test@exemplu.ro',
          'address1' => 'Strada Exemplu 123',
          'address2' => 'Etaj 2, Birou 45',
          'city' => 'Bucharest',
          'state' => 'Bucharest',
          'postal_code' => '010101',
          'country_id' => '642', // Romania
          'shipping_address1' => 'Strada Exemplu 123',
          'shipping_address2' => 'Etaj 2, Birou 45',
          'shipping_city' => 'Bucharest',
          'shipping_state' => 'Bucharest',
          'shipping_postal_code' => '010101',
          'shipping_country_id' => '642', // Romania
          'settings' => ClientSettings::defaults(),
          'client_hash' => \Illuminate\Support\Str::random(32),
          'routing_id' => 'SCSCSCS',
          'classification' => 'business',
        ]);

        ClientContact::factory()->create([
          'company_id' => $company->id,
          'user_id' => $this->user->id,
          'client_id' => $c->id,
          'first_name' => 'Contact First',
          'last_name' => 'Contact Last',
          'email' => 'david+c1@invoiceninja.com',
        ]);


        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->tax_rate1 = 19;
        $item->tax_name1 = 'TVA';

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $c->id,
            'discount' => 0,
            'uses_inclusive_taxes' => false,
            'status_id' => 1,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name2' => '',
            'tax_name3' => '',
            'line_items' => [$item],
            'number' => 'IT-'.rand(1000, 100000),
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        return $invoice;

    }

    public function testRoRules()
    {
        $invoice = $this->createROData();

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach ($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertInstanceof(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $e_invoice);

        $p = new Peppol($invoice);

        $p->run();
        $xml  = $p->toXml();
        // nlog($xml);

        // $identifiers = $p->getStorecoveMeta();

        // $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        // $sc->sendDocument($xml, $this->routing_id, $identifiers);

    }




    public function PestAtGovernmentRules()
    {
        $this->routing_id = 293801;

        $invoice = $this->createATData(true);

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach ($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertInstanceof(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $e_invoice);

        $p = new Peppol($invoice);

        $p->run();
        $xml  = $p->toXml();
        // nlog($xml);

        $identifiers = $p->getStorecoveMeta();

        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($xml, $this->routing_id, $identifiers);

    }

    public function PtestItRules()
    {
        $invoice = $this->createITData();

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach ($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertInstanceof(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $e_invoice);

        $p = new Peppol($invoice);

        $p->run();
        $xml  = $p->toXml();
        // nlog($xml);

        $identifiers = $p->getStorecoveMeta();

        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($xml, $this->routing_id, $identifiers);


        //test individual sending

        // nlog("Individual");

        $invoice = $this->createITData(false);

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach ($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();

        $p = new Peppol($invoice);

        $p->run();
        $xml  = $p->toXml();
        // nlog($xml);

        $identifiers = $p->getStorecoveMeta();

        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($xml, $this->routing_id, $identifiers);


    }

    public function PestAtRules()
    {
        $this->routing_id = 293801;

        $invoice = $this->createATData();

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach ($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertInstanceof(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $e_invoice);

        $p = new Peppol($invoice);

        $p->run();
        $xml  = $p->toXml();
        // nlog($xml);

        $identifiers = $p->getStorecoveMeta();

        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($xml, $this->routing_id, $identifiers);

    }

    public function PtestFrRules()
    {

        $invoice = $this->createFRData();

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach ($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertInstanceof(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $e_invoice);

        $p = new Peppol($invoice);

        $p->run();
        $xml  = $p->toXml();
        // nlog($xml);

        $identifiers = $p->getStorecoveMeta();

        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($xml, $this->routing_id, $identifiers);

    }

    public function PtestEsRules()
    {

        $invoice = $this->createESData();

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach ($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertInstanceof(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $e_invoice);

        $p = new Peppol($invoice);

        $p->run();
        $xml  = $p->toXml();
        // nlog($xml);

        $identifiers = [
          "routing" => [
            "eIdentifiers" => [
                [
                'scheme' => 'ES:VAT',
                'id' => 'ESB53625999'
                ],
            ]
          ]
        ];

        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($xml, $this->routing_id, $identifiers);

    }

    public function RtestDeRules()
    {
        $invoice = $this->createDEData();

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach ($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertInstanceof(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $e_invoice);

        $p = new Peppol($invoice);

        $p->run();
        $xml  = $p->toXml();
        // nlog($xml);

        $identifiers = [
          "routing" => [
            "eIdentifiers" => [
              [
                'scheme' => 'DE:VAT',
                'id' => 'DE010101010'
              ]
            ]
          ]
        ];

        $sc = new \App\Services\EDocument\Gateway\Storecove\Storecove();
        $sc->sendDocument($xml, $this->routing_id, $identifiers);


    }

    /**
     * testSgToInReceiverUsesEmailRouting
     *
     * When sending SG -> IN, the Indian recipient's routing code resolves to "Email".
     * The routing payload must use emails (not eIdentifiers with the GSTIN).
     */
    /**
     * testBeToSgPublicIdentifierUsesUenScheme
     *
     * When sending BE -> SG, the Singapore client's vat_number (a UEN like
     * "SGTST123457890SC") must be sent as scheme SG:UEN, NOT SG:GST.
     * SG:GST expects a hyphenated format (XX-0000000-X) which a UEN does not match.
     */
    public function testBeToSgPublicIdentifierUsesUenScheme(): void
    {
        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => 'BE0202239951',
            'company_id_number' => '0202239951',
            'company_country' => 'BE',
            'company_classification' => 'business',
            'client_country' => 'SG',
            'client_vat' => 'SGTST123457890SC',
            'client_id_number' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];

        $data = $this->setupTestData($scenario);
        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        $storecove = new Storecove();
        $adapter = $storecove->adapter;
        $adapter->transform($invoice)->decorate();

        $customerParty = $adapter->getInvoice()->getAccountingCustomerParty();
        $publicIdentifiers = $customerParty->getPublicIdentifiers();

        $this->assertNotNull($publicIdentifiers, 'SG client must have a publicIdentifier');
        $this->assertNotEmpty($publicIdentifiers, 'SG client must have at least one publicIdentifier');

        $pi = $publicIdentifiers[0];
        $this->assertEquals('SG:UEN', $pi->getScheme(), 'SG client with UEN in vat_number must use SG:UEN scheme, not SG:GST');
        $this->assertEquals('SGTST123457890SC', $pi->getId(), 'Identifier value must be the cleaned UEN');
    }

    /**
     * testBeToSgPublicIdentifierPrefersIdNumberForUen
     *
     * SG routing is SG:UEN. When a SG client has both a UEN in id_number
     * and a GST in vat_number, the UEN (id_number) must be used since
     * SG:UEN is a non-VAT scheme.
     */
    public function testBeToSgPublicIdentifierPrefersIdNumberForUen(): void
    {
        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => 'BE0202239951',
            'company_id_number' => '0202239951',
            'company_country' => 'BE',
            'company_classification' => 'business',
            'client_country' => 'SG',
            'client_vat' => 'M2-1234567-8',
            'client_id_number' => 'T08GA0028A',
            'classification' => 'business',
            'has_valid_vat' => false,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];

        $data = $this->setupTestData($scenario);
        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        $storecove = new Storecove();
        $adapter = $storecove->adapter;
        $adapter->transform($invoice)->decorate();

        $customerParty = $adapter->getInvoice()->getAccountingCustomerParty();
        $publicIdentifiers = $customerParty->getPublicIdentifiers();

        $this->assertNotNull($publicIdentifiers, 'SG client must have a publicIdentifier');
        $this->assertNotEmpty($publicIdentifiers, 'SG client must have at least one publicIdentifier');

        $pi = $publicIdentifiers[0];
        $this->assertEquals('SG:UEN', $pi->getScheme(), 'SG routing always uses SG:UEN');
        $this->assertEquals('T08GA0028A', $pi->getId(), 'Must prefer id_number (UEN) over vat_number (GST) for non-VAT scheme');
    }

    /**
     * testSgToSgGovernmentPublicIdentifierWithClientUen
     *
     * SG B2G routing uses the fixed endpoint "0195:SGUENT08GA0028A".
     * When the client has a UEN in id_number, the publicIdentifier
     * must use SG:UEN with the client's actual UEN.
     */
    public function testSgToSgGovernmentPublicIdentifierWithClientUen(): void
    {
        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => '',
            'company_id_number' => 'T08GA0028A',
            'company_country' => 'SG',
            'company_classification' => 'business',
            'client_country' => 'SG',
            'client_vat' => '',
            'client_id_number' => 'T09CC0032B',
            'classification' => 'government',
            'has_valid_vat' => false,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];

        $data = $this->setupTestData($scenario);
        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        $storecove = new Storecove();
        $adapter = $storecove->adapter;
        $adapter->transform($invoice)->decorate();

        $customerParty = $adapter->getInvoice()->getAccountingCustomerParty();
        $publicIdentifiers = $customerParty->getPublicIdentifiers();

        $this->assertNotNull($publicIdentifiers, 'SG B2G client must have a publicIdentifier');
        $this->assertNotEmpty($publicIdentifiers, 'SG B2G client must have at least one publicIdentifier');

        $pi = $publicIdentifiers[0];
        $this->assertEquals('SG:UEN', $pi->getScheme(), 'SG B2G must use SG:UEN scheme');
        $this->assertEquals('T09CC0032B', $pi->getId(), 'SG:UEN should contain the client UEN');
    }

    /**
     * testSgToSgGovernmentPublicIdentifierFallsToCentralisedId
     *
     * SG B2G routing uses the fixed endpoint "0195:SGUENT08GA0028A".
     * When the client has NO UEN, the publicIdentifier must fall back
     * to the centralised endpoint ID "SGUENT08GA0028A".
     */
    public function testSgToSgGovernmentPublicIdentifierFallsToCentralisedId(): void
    {
        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => '',
            'company_id_number' => 'T08GA0028A',
            'company_country' => 'SG',
            'company_classification' => 'business',
            'client_country' => 'SG',
            'client_vat' => '',
            'client_id_number' => '',
            'classification' => 'government',
            'has_valid_vat' => false,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];

        $data = $this->setupTestData($scenario);
        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        $storecove = new Storecove();
        $adapter = $storecove->adapter;
        $adapter->transform($invoice)->decorate();

        $customerParty = $adapter->getInvoice()->getAccountingCustomerParty();
        $publicIdentifiers = $customerParty->getPublicIdentifiers();

        $this->assertNotNull($publicIdentifiers, 'SG B2G with no client UEN must still have a publicIdentifier');
        $this->assertNotEmpty($publicIdentifiers, 'SG B2G with no client UEN must have at least one publicIdentifier');

        $pi = $publicIdentifiers[0];
        $this->assertEquals('SG:UEN', $pi->getScheme(), 'SG B2G fallback must use SG:UEN scheme');
        $this->assertEquals('SGUENT08GA0028A', $pi->getId(), 'Must fall back to centralised endpoint ID');
    }

    /**
     * testBeClientWithIdNumberUsesEnScheme
     *
     * BE routing is BE:EN (enterprise number). When the client has an id_number
     * (bare enterprise number like "1000000417"), it must be sent as BE:EN.
     */
    public function testBeClientWithIdNumberUsesEnScheme(): void
    {
        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => '',
            'company_id_number' => 'T08GA0028A',
            'company_country' => 'SG',
            'company_classification' => 'business',
            'client_country' => 'BE',
            'client_vat' => 'BE1000000417',
            'client_id_number' => '1000000417',
            'classification' => 'business',
            'has_valid_vat' => false,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];

        $data = $this->setupTestData($scenario);
        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        $storecove = new Storecove();
        $adapter = $storecove->adapter;
        $adapter->transform($invoice)->decorate();

        $customerParty = $adapter->getInvoice()->getAccountingCustomerParty();
        $publicIdentifiers = $customerParty->getPublicIdentifiers();

        $this->assertNotNull($publicIdentifiers, 'BE client must have a publicIdentifier');
        $this->assertNotEmpty($publicIdentifiers, 'BE client must have at least one publicIdentifier');

        $pi = $publicIdentifiers[0];
        $this->assertEquals('BE:EN', $pi->getScheme(), 'BE routing uses BE:EN (enterprise number), not BE:VAT');
        $this->assertEquals('1000000417', $pi->getId(), 'Enterprise number must be bare 10 digits without BE prefix');
    }

    /**
     * testBeClientWithOnlyVatNumberStripsPrefix
     *
     * When a BE client has only a VAT number "BE1000000417" and no id_number,
     * the country prefix "BE" is stripped to produce "1000000417" which matches
     * BE:EN format (^[01]\d{9}$).
     */
    public function testBeClientWithOnlyVatNumberStripsPrefix(): void
    {
        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => '',
            'company_id_number' => 'T08GA0028A',
            'company_country' => 'SG',
            'company_classification' => 'business',
            'client_country' => 'BE',
            'client_vat' => 'BE1000000417',
            'client_id_number' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];

        $data = $this->setupTestData($scenario);
        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        $storecove = new Storecove();
        $adapter = $storecove->adapter;
        $adapter->transform($invoice)->decorate();

        $customerParty = $adapter->getInvoice()->getAccountingCustomerParty();
        $publicIdentifiers = $customerParty->getPublicIdentifiers();

        $this->assertNotEmpty($publicIdentifiers, 'BE client with VAT number should have publicIdentifier after prefix stripping');
        $pi = $publicIdentifiers[0];
        $this->assertEquals('BE:EN', $pi->getScheme(), 'BE routing uses BE:EN');
        $this->assertEquals('1000000417', $pi->getId(), 'BE prefix must be stripped — Storecove requires bare 10-digit enterprise number');
    }

    /**
     * When a BE client has an invalid id_number (e.g. "0003" — an ICD scheme code
     * rather than a real enterprise number), the Mutator should fall back to
     * vat_number for routing instead of sending the invalid value.
     */
    public function testBeClientWithInvalidIdNumberFallsBackToVatNumber(): void
    {
        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => '',
            'company_id_number' => 'T08GA0028A',
            'company_country' => 'SG',
            'company_classification' => 'business',
            'client_country' => 'BE',
            'client_vat' => 'BE0202239951',
            'client_id_number' => '0003',
            'classification' => 'business',
            'has_valid_vat' => false,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];

        $data = $this->setupTestData($scenario);
        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        // Adapter publicIdentifier should use vat_number fallback
        $storecove = new Storecove();
        $adapter = $storecove->adapter;
        $adapter->transform($invoice)->decorate();

        $customerParty = $adapter->getInvoice()->getAccountingCustomerParty();
        $publicIdentifiers = $customerParty->getPublicIdentifiers();

        $this->assertNotEmpty($publicIdentifiers, 'BE client with invalid id_number should still get a publicIdentifier via vat_number fallback');
        $pi = $publicIdentifiers[0];
        $this->assertEquals('BE:EN', $pi->getScheme());
        $this->assertEquals('0202239951', $pi->getId(), 'Should use enterprise number derived from vat_number, not invalid id_number "0003"');

        // Mutator routing should also use the vat_number-derived identifier
        $p = new Peppol($invoice);
        $p->run();
        $identifiers = $p->gateway->mutator->setClientRoutingCode()->getStorecoveMeta();

        $this->assertArrayHasKey('routing', $identifiers);
        $routing = $identifiers['routing'];
        $this->assertArrayHasKey('eIdentifiers', $routing);

        $eId = $routing['eIdentifiers'][0];
        $this->assertNotEquals('0003', $eId['id'], 'Invalid id_number "0003" must not be used for routing');
    }

    /**
     * Tests that resolveRouting() returns the correct scheme for every country
     * and that the public identifier resolution picks the right value source.
     *
     * Each entry: [country, classification, client_vat, client_id_number, client_routing_id,
     *              expected_scheme (null = skip), expected_id (null = skip)]
     */
    public static function publicIdentifierCountryProvider(): array
    {
        return [
            // VAT-routed countries (scheme = :VAT, prefer vat_number)
            'AD business' => ['AD', 'business', 'A123456B', '', '', 'AD:VAT', 'A123456B'],
            'AL business' => ['AL', 'business', 'K12345678L', '', '', 'AL:VAT', 'K12345678L'],
            'AT business' => ['AT', 'business', 'ATU12345678', '', '', 'AT:VAT', 'ATU12345678'],
            'BA business' => ['BA', 'business', '123456789012', '', '', 'BA:VAT', '123456789012'],
            'BG business' => ['BG', 'business', 'BG123456789', '', '', 'BG:VAT', 'BG123456789'],
            'CY business' => ['CY', 'business', 'CY12345678A', '', '', 'CY:VAT', 'CY12345678A'],
            'CZ business' => ['CZ', 'business', 'CZ12345678', '', '', 'CZ:VAT', 'CZ12345678'],
            'DE business' => ['DE', 'business', 'DE123456789', '', '', 'DE:VAT', 'DE123456789'],
            'ES business' => ['ES', 'business', 'ESA1234567B', '', '', 'ES:VAT', 'ESA1234567B'],
            'GB business' => ['GB', 'business', 'GB123456789', '', '', 'GB:VAT', 'GB123456789'],
            'GR business' => ['GR', 'business', 'EL123456789', '', '', 'GR:VAT', 'EL123456789'],
            'HR business' => ['HR', 'business', 'HR12345678901', '', '', 'HR:VAT', 'HR12345678901'],
            'HU business' => ['HU', 'business', 'HU12345678', '', '', 'HU:VAT', 'HU12345678'],
            'IE business' => ['IE', 'business', 'IE1A23456B', '', '', 'IE:VAT', 'IE1A23456B'],
            'LI business' => ['LI', 'business', 'LI12345', '', '', 'LI:VAT', 'LI12345'],
            'LU business' => ['LU', 'business', 'LU12345678', '', '', 'LU:VAT', 'LU12345678'],
            'LV business' => ['LV', 'business', 'LV12345678901', '', '', 'LV:VAT', 'LV12345678901'],
            'MC business' => ['MC', 'business', 'FR12345678901', '', '', 'MC:VAT', 'FR12345678901'],
            'ME business' => ['ME', 'business', 'ME12345678', '', '', 'ME:VAT', 'ME12345678'],
            'MK business' => ['MK', 'business', 'MK1234567890123', '', '', 'MK:VAT', 'MK1234567890123'],
            'MT business' => ['MT', 'business', 'MT12345678', '', '', 'MT:VAT', 'MT12345678'],
            'PL business' => ['PL', 'business', 'PL1234567890', '', '', 'PL:VAT', 'PL1234567890'],
            'PT business' => ['PT', 'business', 'PT123456789', '', '', 'PT:VAT', 'PT123456789'],
            'RO business' => ['RO', 'business', 'RO1234567890', '', '', 'RO:VAT', 'RO1234567890'],
            'RS business' => ['RS', 'business', 'RS123456789', '', '', 'RS:VAT', 'RS123456789'],
            'SI business' => ['SI', 'business', 'SI12345678', '', '', 'SI:VAT', 'SI12345678'],
            'SK business' => ['SK', 'business', 'SK1234567890', '', '', 'SK:VAT', 'SK1234567890'],
            'SM business' => ['SM', 'business', 'SM12345', '', '', 'SM:VAT', 'SM12345'],
            'TR business' => ['TR', 'business', 'TR1234567890', '', '', 'TR:VAT', 'TR1234567890'],
            'VA business' => ['VA', 'business', 'VA12345678901', '', '', 'VA:VAT', 'VA12345678901'],
            'NL business' => ['NL', 'business', 'NL123456789B01', '', '', 'NL:VAT', 'NL123456789B01'],

            // VAT-routed with fallback to id_number
            'DE vat_only'       => ['DE', 'business', 'DE123456789', '', '', 'DE:VAT', 'DE123456789'],
            'DE id_fallback'    => ['DE', 'business', '', '123456789', '', 'DE:VAT', '123456789'], // id_number matches DE:VAT as fallback

            // Non-VAT-routed countries (prefer id_number)
            'BE with id'        => ['BE', 'business', 'BE1000000417', '1000000417', '', 'BE:EN', '1000000417'],
            'BE vat fallback'   => ['BE', 'business', 'BE1000000417', '', '', 'BE:EN', '1000000417'], // BE prefix stripped from vat_number fallback
            'SE with id'        => ['SE', 'business', 'SE123456789012', '1234567890', '', 'SE:ORGNR', '1234567890'],
            'SE vat fallback'   => ['SE', 'business', 'SE123456789012', '', '', null, null], // VAT doesn't match SE:ORGNR
            'DK with id'        => ['DK', 'business', 'DK12345678', 'DK12345678', '', 'DK:DIGST', 'DK12345678'],
            'EE with id'        => ['EE', 'business', 'EE123456789', '12345678', '', 'EE:CC', '12345678'],
            'NO with id'        => ['NO', 'business', 'NO123456789', '123456789', '', 'NO:ORG', '123456789'],
            'FI with id'        => ['FI', 'business', 'FI12345678', '123456789012', '', 'FI:OVT', '123456789012'],
            'LT with id'        => ['LT', 'business', 'LT123456789', '1234567', '', 'LT:LEC', '1234567'],
            'IS with id'        => ['IS', 'business', 'IS123456', '1234567890', '', 'IS:KTNR', '1234567890'],
            'CH with id'        => ['CH', 'business', 'CHE123456789MWST', 'CHE123456789', '', 'CH:UIDB', 'CHE123456789'],
            'JP with id'        => ['JP', 'business', 'T1234567890123', 'T1234567890123', '', 'JP:SST', 'T1234567890123'],
            'MY with id'        => ['MY', 'business', 'MY1234567890', 'A1B2C3D4E5', '', 'MY:EIF', 'A1B2C3D4E5'],
            'SG with id'        => ['SG', 'business', 'M2-1234567-8', 'T08GA0028A', '', 'SG:UEN', 'T08GA0028A'],
            'SG vat fallback'   => ['SG', 'business', 'SGTST123457890SC', '', '', 'SG:UEN', 'SGTST123457890SC'],
            'CA with id'        => ['CA', 'business', '123456789', '123456789', '', 'CA:CBN', '123456789'],
            'AU with id'        => ['AU', 'business', '12345678901', '12345678901', '', 'AU:ABN', '12345678901'],
            'MX with id'        => ['MX', 'business', 'XAXX010101000', 'XAXX010101000', '', 'MX:RFC', 'XAXX010101000'],

            // Email-routed — routing via email, but tax identifier still required in publicIdentifiers
            'IN business'       => ['IN', 'business', '22AAAAA0000A1Z5', '', '', 'IN:GSTIN', '22AAAAA0000A1Z5'],
            'SA business'       => ['SA', 'business', '1234567890', '', '', 'SA:TIN', '1234567890'],

            // Government with composite/fixed endpoints — falls back to identifier scheme (column 1)
            'AT government'     => ['AT', 'government', '', 'AT:GOV-ID', '', 'AT:GOV', 'AT:GOV-ID'],
            'SG government'     => ['SG', 'government', '', 'T08GA0028A', '', 'SG:UEN', 'T08GA0028A'],

            // IT:CUUO uses routing_id
            'IT business'       => ['IT', 'business', 'IT12345678901', '', 'A1B2C3', 'IT:CUUO', 'A1B2C3'],
            'IT no routing_id'  => ['IT', 'business', 'IT12345678901', '', '', null, null],
        ];
    }

    #[DataProvider('publicIdentifierCountryProvider')]
    public function testPublicIdentifierResolution(
        string $clientCountry,
        string $classification,
        string $clientVat,
        string $clientIdNumber,
        string $clientRoutingId,
        ?string $expectedScheme,
        ?string $expectedId,
    ): void {
        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => 'DE123456789',
            'company_id_number' => '',
            'company_country' => 'DE',
            'company_classification' => 'business',
            'client_country' => $clientCountry,
            'client_vat' => $clientVat,
            'client_id_number' => $clientIdNumber,
            'classification' => $classification,
            'has_valid_vat' => false,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];

        $data = $this->setupTestData($scenario);
        $client = $data['client'];

        // Set routing_id explicitly (clear factory default when empty)
        $client->routing_id = $clientRoutingId ?: null;
        $client->save();

        $invoice = $data['invoice'];
        $invoice->setRelation('client', $client->fresh());
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        $storecove = new Storecove();
        $adapter = $storecove->adapter;
        $adapter->transform($invoice)->decorate();

        $customerParty = $adapter->getInvoice()->getAccountingCustomerParty();
        $publicIdentifiers = $customerParty->getPublicIdentifiers();

        if ($expectedScheme === null) {
            $this->assertTrue(
                empty($publicIdentifiers),
                "Expected no publicIdentifier for {$clientCountry} {$classification}, but got one"
            );
        } else {
            $this->assertNotEmpty($publicIdentifiers, "Expected publicIdentifier for {$clientCountry} {$classification}");
            $pi = $publicIdentifiers[0];
            $this->assertEquals($expectedScheme, $pi->getScheme(), "Wrong scheme for {$clientCountry} {$classification}");
            $this->assertEquals($expectedId, $pi->getId(), "Wrong identifier value for {$clientCountry} {$classification}");
        }
    }

    public function testSgToInReceiverUsesEmailRouting(): void
    {
        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => '',
            'company_id_number' => 'T08GA0028A',
            'company_country' => 'SG',
            'company_classification' => 'business',
            'client_country' => 'IN',
            'client_vat' => '22AAAAA0000A1Z5',
            'client_id_number' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();
        $client = $data['client'];

        $this->assertEquals('SG', $data['company']->country()->iso_3166_2);
        $this->assertEquals('IN', $client->country->iso_3166_2);

        $invoice->save();

        $p = new Peppol($invoice);
        $p->run();

        $identifiers = $p->gateway->mutator->setClientRoutingCode()->getStorecoveMeta();

        // Must use email routing, NOT eIdentifiers
        $this->assertArrayHasKey('routing', $identifiers);
        $this->assertArrayHasKey('emails', $identifiers['routing'], 'IN receiver should use email routing, not eIdentifiers');
        $this->assertArrayNotHasKey('eIdentifiers', $identifiers['routing'], 'IN receiver should not have eIdentifiers — GSTIN must not be sent as an email-scheme identifier');

        // The email should be the client contact's email
        $contactEmail = $client->present()->email();
        $this->assertContains($contactEmail, $identifiers['routing']['emails']);

        // Peppol XML EndpointID must use a valid EAS code (0088/GLN), not "Email"
        $peppolInvoice = $p->getInvoice();
        $endpointId = $peppolInvoice->AccountingCustomerParty->Party->EndpointID;
        $this->assertEquals('0202', $endpointId->schemeID, 'EndpointID schemeID must be 0202 for email-routed countries');
        $this->assertEquals('22AAAAA0000A1Z5', $endpointId->value, 'EndpointID value must be the client GSTIN for IN receivers');
    }

    /**
     * testSgToInPublicIdentifierUsesGstinTaxScheme
     *
     * IN routing is "Email" but Storecove still requires a tax identifier
     * (IN:GSTIN) in publicIdentifiers. Verify the adapter falls back to
     * resolveTaxScheme() and sends the GSTIN.
     */
    public function testSgToInPublicIdentifierUsesGstinTaxScheme(): void
    {
        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => '',
            'company_id_number' => 'T08GA0028A',
            'company_country' => 'SG',
            'company_classification' => 'business',
            'client_country' => 'IN',
            'client_vat' => '22AAAAA0000A1Z5',
            'client_id_number' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];

        $data = $this->setupTestData($scenario);
        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        $storecove = new Storecove();
        $adapter = $storecove->adapter;
        $adapter->transform($invoice)->decorate();

        $customerParty = $adapter->getInvoice()->getAccountingCustomerParty();
        $publicIdentifiers = $customerParty->getPublicIdentifiers();

        $this->assertNotEmpty($publicIdentifiers, 'IN client must have a publicIdentifier with tax scheme even though routing is via Email');

        $pi = $publicIdentifiers[0];
        $this->assertEquals('IN:GSTIN', $pi->getScheme(), 'IN routing is Email but publicIdentifier must use IN:GSTIN tax scheme');
        $this->assertEquals('22AAAAA0000A1Z5', $pi->getId(), 'GSTIN value must be sent as the identifier');
    }

    /**
     * testUsGlnToDeExemptTaxCategoriesAreOutsideScope
     *
     * US sender (GLN, no VAT number) => DE client with tax-exempt line items.
     * Every tax reference in the Storecove document (line items, tax subtotals,
     * serialised payload) must use 'outside_scope' — never a VAT concept like
     * 'zero_rated', 'export', or 'standard' — because the sender has no VAT number.
     */
    public function testUsGlnToDeExemptTaxCategoriesAreOutsideScope(): void
    {
        $this->routing_id = 290868;

        $scenario = [
            'company_vat' => '',
            'company_id_number' => '',
            'company_country' => 'US',
            'company_classification' => 'business',
            'client_country' => 'DE',
            'client_vat' => 'DE123456789',
            'client_id_number' => '',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => true,
        ];

        $data = $this->setupTestData($scenario);
        $client = $data['client'];
        $client->routing_id = '1234567890123';
        $client->save();

        $invoice = $data['invoice'];
        $invoice->setRelation('client', $client->fresh());

        $line_items = $invoice->line_items;
        foreach ($line_items as &$item) {
            $item->tax_name1 = '';
            $item->tax_rate1 = 0;
            $item->tax_id = '';
        }
        unset($item);

        $invoice->line_items = array_values($line_items);
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        // Build the Peppol XML and check it directly
        $p = new Peppol($invoice);
        $p->run();
        $xml = $p->toXml();

        // The Peppol XML must use tax category 'O' (outside scope), not 'Z' or 'G'
        $this->assertStringContainsString('<cbc:ID>O</cbc:ID>', $xml, 'Peppol XML must use tax category O (outside scope) for non-EU sender');

        // BR-O-10: category O requires TaxExemptionReasonCode and/or TaxExemptionReason
        $this->assertStringContainsString('vatex-eu-o', $xml, 'Peppol XML must contain vatex-eu-o exemption reason code (BR-O-10)');
        $this->assertStringContainsString('Not subject to VAT', $xml, 'Peppol XML must contain exemption reason text (BR-O-10)');

        // Must NOT contain VAT-implying categories Z or G
        $this->assertDoesNotMatchRegularExpression(
            '/<cbc:ID>Z<\/cbc:ID>/',
            $xml,
            'Peppol XML must not use tax category Z (zero rated) for non-EU sender'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<cbc:ID>G<\/cbc:ID>/',
            $xml,
            'Peppol XML must not use tax category G (export) for non-EU sender'
        );

        // ── XSD + XSLT/Schematron validation (BR-O-10 etc.) ──
        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->validate();
        $errors = $validator->getErrors();

        $fatal = [];
        foreach (['xsd', 'stylesheet', 'general'] as $category) {
            foreach ($errors[$category] ?? [] as $msg) {
                if (stripos($msg, '[fatal]') !== false || stripos($msg, '[error]') !== false) {
                    $fatal[] = "[{$category}] {$msg}";
                }
            }
        }

        $this->assertEmpty($fatal, "Peppol validation errors for US=>DE tax-exempt:\n" . implode("\n", $fatal));

        // Now check the Storecove adapter output
        $storecove = new Storecove();
        $adapter = $storecove->adapter;
        $adapter->transform($invoice)->decorate();

        $doc = $adapter->getDocument();
        $this->assertArrayHasKey('document', $doc);
        $this->assertNotFalse($doc['document'], 'Document must not be false (no transform errors)');

        // Check line-level tax categories
        $lines = $adapter->getInvoice()->getInvoiceLines();
        $this->assertNotEmpty($lines, 'Invoice must have line items');

        $vatCategories = ['zero_rated', 'standard', 'export', 'exempt', 'reverse_charge', 'intra_community'];

        foreach ($lines as $i => $line) {
            $taxes = $line->taxes_duties_fees ?? [];
            foreach ($taxes as $tax) {
                $cat = $tax->getCategory();
                $this->assertNotContains($cat, $vatCategories, "Line {$i} tax category '{$cat}' is a VAT concept — must be 'outside_scope' for non-EU sender");
                $this->assertEquals('outside_scope', $cat, "Line {$i} tax category must be 'outside_scope'");
            }
        }

        // Check invoice-level tax subtotals
        $taxSubtotals = $adapter->getInvoice()->getTaxSubtotals();
        if ($taxSubtotals) {
            foreach ($taxSubtotals as $j => $sub) {
                $cat = $sub->getCategory();
                $this->assertNotContains($cat, $vatCategories, "Tax subtotal {$j} category '{$cat}' is a VAT concept — must be 'outside_scope'");
                $this->assertEquals('outside_scope', $cat, "Tax subtotal {$j} must be 'outside_scope'");
            }
        }

        // Check the serialised document has no VAT tax categories
        $json = json_encode($doc['document']);
        $this->assertStringNotContainsString('"zero_rated"', $json, 'Serialised document must not contain zero_rated');
        $this->assertStringNotContainsString('"tax_exempt_reason"', $json, 'Serialised document must not contain tax_exempt_reason');
    }


}
