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

namespace Tests\Feature\EInvoice;

use Faker\Factory;
use Tests\TestCase;
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
use App\Factory\CompanyUserFactory;
use App\Repositories\InvoiceRepository;
use InvoiceNinja\EInvoice\EInvoice;
use App\Services\EDocument\Standards\Peppol;
use Illuminate\Routing\Middleware\ThrottleRequests;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Services\EDocument\Standards\Validation\Peppol\EntityLevel;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;
use InvoiceNinja\EInvoice\Models\Peppol\BranchType\FinancialInstitutionBranch;
use InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount;

class PeppolTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected int $iterations = 10;
    protected function setUp(): void
    {
        parent::setUp();

        if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test for GH Actions');
        }

        try {
            $processor = new \Saxon\SaxonProcessor();
        } catch (\Throwable $e) {
            $this->markTestSkipped('saxon not installed');
        }

        $this->faker = Factory::create();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    private function setupTestData(array $params = []): array
    {

        $settings = CompanySettings::defaults();
        $settings->vat_number = $params['company_vat'] ?? 'DE123456789';
        $settings->country_id = Country::where('iso_3166_2', 'DE')->first()->id;
        $settings->email = $this->faker->safeEmail();
        $settings->currency_id = '3';
        $settings->e_invoice_type = 'PEPPOL'; // Required for validation endpoint to run EntityLevel validation

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

        /** @var Client $client */
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

        $client->setRelation('company', $company);

        /** @var ClientContact $contact */
        $contact = ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $client->company_id,
            'user_id' => $client->user_id,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->safeEmail(),
            'is_primary' => true,
            'send_email' => true,
        ]);

        $client->setRelation('contacts', [$contact]);

        /** @var Invoice $invoice */
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
            'status_id' => Invoice::STATUS_DRAFT,
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

        $invoice->setRelation('client', $client);
        $invoice->setRelation('company', $company);

        return compact('company', 'client', 'invoice');
    }


    // {
    //     "legalEntityId": 100000099999,
    //     "document": {
    //       "documentType": "enveloped_data",
    //       "envelopedData": {
    //         "document": "PEludm9pY2U+PC9JbnZvaWNlPg==",
    //         "application": "peppol",
    //         "processIdSchemeId": "cenbii-procid-ubl",
    //         "processId": "urn:fdc:peppol.eu:2017:poacc:billing:01:1.0",
    //         "documentIdSchemeId": "busdox-docid-qns",
    //         "documentId": "urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1",
    //         "envelope": {
    //           "sender": "9930:DE010101010",
    //           "receiver": "9930:DE010101010",
    //           "requestMls": "on_error"
    //         },
    //         "metadata": {
    //           "documentNumber": "1234567890",
    //           "documentDate": "2025-05-16",
    //           "receiverName": "John Doe",
    //           "receiverCountry": "DE",
    //           "payloadType": "Invoice"
    //         }
    //       }
    //     }
    //   }

    /**
     * Stubbed if and when we need to send the raw XML
     * due to Storecoves inability to handle special features:
     * 
     * ie: attaching documents in base64. 
     *
     * @return void
     */
    public function envelopedMode()
    {
        
    }

    public function testBeToBeWithSpecialLineItemConfiguration()
    {

        $settings = CompanySettings::defaults();
        $settings->address1 = 'Dudweilerstr. 34b';
        $settings->city = 'Ost Alessa';
        $settings->state = 'Bayern';
        $settings->postal_code = '98060';
        $settings->vat_number = 'BE923356489';
        $settings->id_number = '991-00110-12';
        $settings->country_id = '56';
        $settings->currency_id = '3';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new FinancialInstitutionBranch();
        $fib->ID = "DEUTDEMMXXX"; //BIC
        // $fib->Name = 'Deutsche Bank';

        $pfa = new PayeeFinancialAccount();
        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $id->value = 'DE89370400440532013000';
        $pfa->ID = $id;
        $pfa->Name = 'PFA-NAME';

        $pfa->FinancialInstitutionBranch = $fib;

        $pm = new PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;

        $pmc = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode();
        $pmc->value = '30';

        $pm->PaymentMeansCode = $pmc;

        $einvoice->PaymentMeans[] = $pm;

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'e_invoice' => $stub,
        ]);

        $cu = CompanyUserFactory::create($this->user->id, $company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = false;
        $cu->save();

        $client_settings = ClientSettings::defaults();
        $client_settings->currency_id = '3';

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'German Client Name',
            'address1' => 'Kinderhausen 96b',
            'address2' => 'Apt. 842',
            'city' => 'Süd Jessestadt',
            'state' => 'Bayern',
            'postal_code' => '33323',
            'country_id' => 56,
            'routing_id' => 'ABC1234',
            'settings' => $client_settings,
            'vat_number' => 'BE173655434',
        ]);

            $item = new InvoiceItem();
            $item->product_key = "Product Key";
            $item->notes = "Product Description";
            $item->cost = 795;
            $item->quantity = 13.5;
            $item->discount = 0;
            $item->is_amount_discount = false;
            $item->tax_rate1 = 21;
            $item->tax_name1 = 'TVA';

            $item2 = new InvoiceItem();
            $item2->product_key = "Product Key 2";
            $item2->notes = "Product Description 2";
            $item2->cost = 795;
            $item2->quantity = 2;
            $item2->discount = 0;
            $item2->is_amount_discount = false;
            $item2->tax_rate1 = 21;
            $item2->tax_name1 = 'TVA';

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'discount' => 0,
            'uses_inclusive_taxes' => false,
            'status_id' => 1,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name2' => '',
            'tax_name3' => '',
            'line_items' => [$item, $item2],
            'number' => 'DE-'.rand(1000, 100000),
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'is_amount_discount' => false,
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $repo = new InvoiceRepository();
        $invoice = $repo->save([], $invoice);

        $invoice->service()->markSent()->save();

        $this->assertEquals(14910.23, $invoice->amount);
        $this->assertEquals(2587.73, $invoice->total_taxes);

        $peppol = new Peppol($invoice);
        $peppol->setInvoiceDefaults();
        $peppol->run();

        $be_invoice = $peppol->getDocument();

        $this->assertNotNull($be_invoice);

        $e = new EInvoice();
        $xml = $e->encode($be_invoice, 'xml');

        $this->assertNotNull($xml);

        $errors = $e->validate($be_invoice);

        if (count($errors) > 0) {
            nlog($xml);
            nlog($errors);
        }

        $this->assertCount(0, $errors);

        $xml = $peppol->toXml();

        try {
            $processor = new \Saxon\SaxonProcessor();
        } catch (\Throwable $e) {
            $this->markTestSkipped('saxon not installed');
        }

        $validator = new XsltDocumentValidator($xml);
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }

    public function testInvoicePeriodValidation()
    {

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923256489',
            'client_id_number' => '123456789',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];

        $entity_data = $this->setupTestData($scenario);

        $invoice = $entity_data['invoice'];

        $data = $invoice->toArray();

        $data['e_invoice'] = [
            'Invoice' => [
             'InvoicePeriod' => [
                [
                    'StartDate' => '-01',
                    'EndDate' => 'boop',
                    'Description' => 'Mustafa',
                    'HelterSkelter' => 'sif'
                ]
             ]
            ]
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/'.$invoice->hashed_id, $data);

        $response->assertStatus(422);

    }

    public function testInvoiceValidationWithSmallDiscount()
    {
        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923256489',
            'client_id_number' => '123456789',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];


        $entity_data = $this->setupTestData($scenario);

        $invoice = $entity_data['invoice'];

        $invoice->is_amount_discount = true;
        $invoice->discount = 0;
        $invoice->uses_inclusive_taxes = false;

        $item = new InvoiceItem();
        $item->quantity = 1;
        $item->cost = 10000;
        $item->product_key = 'test';
        $item->notes = 'Description';
        $item->is_amount_discount = true;
        $item->discount = 1;
        $item->tax_id = '1';

        $invoice->line_items = [$item];

        $ii = $invoice->calc()->getInvoice();

        $repo = new InvoiceRepository();
        $ii = $repo->save([], $ii);
        $this->assertEquals(floatval(11898.81), $ii->amount);

        $company = $entity_data['company'];
        $settings = $company->settings;

        $settings->address1 = 'some address';
        $settings->city = 'some city';
        $settings->postal_code = '102394';

        $company->settings = $settings;
        $company->save();

        $invoice->setRelation('company', $company);
        $invoice->setRelation('client', $entity_data['client']);
        $invoice->save();

        $repo = new InvoiceRepository();
        $invoice = $repo->save([], $invoice);

        $invoice = $invoice->service()->markSent()->save();

        $this->assertGreaterThan(0, $invoice->invitations()->count());

        $data = [
            'entity' => 'invoices',
            'entity_id' => $invoice->hashed_id
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/einvoice/validateEntity', $data);

        if ($response->getStatusCode() !== 200) {

            $p = new Peppol($invoice);
            nlog($p->run()->toXml());
            nlog($invoice->withoutRelations()->toArray());
            nlog($response->json());
        }

        $response->assertStatus(200);

    }


    public function testEntityValidationFailsForInvoiceViaInvoice()
    {
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


        $entity_data = $this->setupTestData($scenario);

        $invoice = $entity_data['invoice'];
        $invoice->number = null;
        $invoice->save();

        $repo = new InvoiceRepository();
        $invoice = $repo->save([], $invoice);

        $data = [
            'entity' => 'invoices',
            'entity_id' => $invoice->hashed_id
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/einvoice/validateEntity', $data);

        $response->assertStatus(422);

    }

    public function testEntityValidationFailsForClientViaClient()
    {
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


        $entity_data = $this->setupTestData($scenario);

        $invoice = $entity_data['invoice'];
        $client = $entity_data['client'];
        $client->address1 = '';
        $client->city = '';
        $client->save();

        // Reload the client to ensure changes are persisted
        $client = $client->refresh();

        // Direct EntityLevel test to debug validation
        $entityLevel = new EntityLevel();
        $directResult = $entityLevel->checkClient($client);
         
        // Assert direct validation fails
        $this->assertFalse($directResult['passes'], 'Direct EntityLevel validation should fail when address1 and city are empty');
        $this->assertNotEmpty($directResult['client'], 'Direct EntityLevel should have client validation errors');

        $data = [
            'entity' => 'clients',
            'entity_id' => $client->hashed_id
        ];

        
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/einvoice/validateEntity', $data);

        // Log the response for debugging
       
        $response->assertStatus(422);

    }

    public function testEntityLevelDirectlyValidatesClientWithMissingAddress()
    {
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

        $entity_data = $this->setupTestData($scenario);
        $client = $entity_data['client'];
        
        // Clear required address fields
        $client->address1 = '';
        $client->city = '';
        $client->save();

        // Directly instantiate and test EntityLevel
        $entityLevel = new EntityLevel();
        $result = $entityLevel->checkClient($client);

        // Assert validation fails
        $this->assertFalse($result['passes'], 'Validation should fail when address1 and city are empty');
        $this->assertNotEmpty($result['client'], 'Should have client validation errors');
        
        // Check that address errors are present
        $errorFields = array_column($result['client'], 'field');
        $this->assertContains('address1', $errorFields, 'Should have address1 error');
        $this->assertContains('city', $errorFields, 'Should have city error');
    }

    /**
     * Test VAT number validation for various EU countries with valid formats.
     * Tests that valid VAT numbers pass validation.
     */
    public function testVatNumberValidationWithValidFormats()
    {
        $validVatNumbers = [
            'AT' => ['ATU12345678', 'U12345678', 'AT U 12345678', 'U-123-456-78'], // U + 8 digits
            'BE' => ['BE0123456789', '0123456789', 'BE 0 123456789', '0-123-456-789'], // 0 + 9 digits
            'BG' => ['BG123456789', '123456789', 'BG 1234567890', '123-456-789-0'], // 9-10 digits
            'CY' => ['CY12345678A', '12345678A', 'CY 12345678 A', '12345678-A'], // 8 digits + 1 letter
            'CZ' => ['CZ12345678', '12345678', 'CZ 1234567890', '123-456-789-0'], // 8-10 digits
            'DE' => ['DE123456789', '123456789', 'DE 123456789', '123-456-789'], // 9 digits
            'DK' => ['DK12345678', '12345678', 'DK 12345678', '123-456-78'], // 8 digits
            'EE' => ['EE123456789', '123456789', 'EE 123456789', '123-456-789'], // 9 digits
            'ES' => ['ESA1234567B', 'A1234567B', 'ES A 1234567 B', 'A-123-456-7-B'], // 1 alphanumeric + 7 digits + 1 alphanumeric
            'FI' => ['FI12345678', '12345678', 'FI 12345678', '123-456-78'], // 8 digits
            'FR' => ['FRAA123456789', 'AA123456789', 'FR AA 123456789', 'AA-123-456-789'], // 2 alphanumeric + 9 digits
            'GR' => ['GR123456789', 'EL123456789', 'GR 123456789', 'EL-123-456-789'], // 9 digits
            'HR' => ['HR12345678901', '12345678901', 'HR 12345678901', '123-456-789-01'], // 11 digits
            'HU' => ['HU12345678', '12345678', 'HU 12345678', '123-456-78'], // 8 digits
            'IE' => ['IE1234567A', '1234567A', 'IE 1 234567 A', '1-234-567-A'], // 1 digit + 1 alphanumeric + 5 digits + 1 letter
            'IT' => ['IT12345678901', '12345678901', 'IT 12345678901', '123-456-789-01'], // 11 digits
            'LT' => ['LT123456789', '123456789', 'LT 123456789012', '123-456-789-012'], // 9 or 12 digits
            'LU' => ['LU12345678', '12345678', 'LU 12345678', '123-456-78'], // 8 digits
            'LV' => ['LV12345678901', '12345678901', 'LV 12345678901', '123-456-789-01'], // 11 digits
            'MT' => ['MT12345678', '12345678', 'MT 12345678', '123-456-78'], // 8 digits
            'NL' => ['NL123456789B01', '123456789B01', 'NL 123456789 B 01', '123-456-789-B-01'], // 9 digits + B + 2 digits
            'PL' => ['PL1234567890', '1234567890', 'PL 1234567890', '123-456-789-0'], // 10 digits
            'PT' => ['PT123456789', '123456789', 'PT 123456789', '123-456-789'], // 9 digits
            'RO' => ['RO12345678', '12345678', 'RO 1234567890', '123-456-789-0'], // 2-10 digits
            'SE' => ['SE123456789001', '123456789001', 'SE 123456789001', '123-456-789-001'], // 12 digits
            'SI' => ['SI12345678', '12345678', 'SI 12345678', '123-456-78'], // 8 digits
            'SK' => ['SK1234567890', '1234567890', 'SK 1234567890', '123-456-789-0'], // 10 digits
        ];

        foreach ($validVatNumbers as $countryCode => $vatNumbers) {
            foreach ($vatNumbers as $vatNumber) {
                $scenario = [
                    'company_vat' => 'DE923356489',
                    'company_country' => 'DE',
                    'client_country' => $countryCode,
                    'client_vat' => $vatNumber,
                    'client_id_number' => '123456789',
                    'classification' => 'business',
                    'has_valid_vat' => true,
                    'over_threshold' => true,
                    'legal_entity_id' => 290868,
                    'is_tax_exempt' => false,
                ];

                $entity_data = $this->setupTestData($scenario);
                $client = $entity_data['client'];
                
                // Ensure client has required address fields
                $client->address1 = 'Test Address';
                $client->city = 'Test City';
                $client->postal_code = '12345';
                $client->save();

                $entityLevel = new EntityLevel();
                $result = $entityLevel->checkClient($client);

                $errorFields = array_column($result['client'], 'field');
                $errorLabels = array_column($result['client'], 'label');
                $errorFields = array_column($result['client'], 'field');
                
                // Should not have invalid_vat_number error (check both field and translated label)
                $hasInvalidVatError = in_array('vat_number', $errorFields) && 
                    in_array(ctrans('texts.invalid_vat_number'), $errorLabels);
                $this->assertFalse($hasInvalidVatError, 
                    "VAT number '{$vatNumber}' for country '{$countryCode}' should be valid");
            }
        }
    }

    /**
     * Test VAT number validation for various EU countries with invalid formats.
     * Tests that invalid VAT numbers fail validation.
     */
    public function testVatNumberValidationWithInvalidFormats()
    {
        $invalidVatNumbers = [
            'AT' => ['AT123456789', 'U1234567', 'U1234567890', 'ATU1234'], // Missing U, wrong digit count
            'BE' => ['BE123456789', '123456789', 'BE012345678', '012345678'], // Missing leading 0 or wrong length
            'BG' => ['BG12345678', '12345678', 'BG12345678901', '12345678901'], // Wrong length (not 9-10)
            'CY' => ['CY12345678', '1234567A', '123456789A', '12345678'], // Missing letter, wrong digit count, or no letter
            'CZ' => ['CZ1234567', '1234567', 'CZ12345678901', '12345678901'], // Wrong length (not 8-10)
            'DE' => ['DE12345678', '12345678', 'DE1234567890', '1234567890'], // Wrong length (not 9)
            'DK' => ['DK1234567', '1234567', 'DK123456789', '123456789'], // Wrong length (not 8)
            'EE' => ['EE12345678', '12345678', 'EE1234567890', '1234567890'], // Wrong length (not 9)
            'ES' => ['ES12345678', '12345678', 'ESA123456', 'A123456'], // Wrong format (not 1 alphanumeric + 7 digits + 1 alphanumeric)
            'FI' => ['FI1234567', '1234567', 'FI123456789', '123456789'], // Wrong length (not 8)
            'FR' => ['FRAA12345678', 'AA12345678', 'FR12345678', '12345678'], // Wrong length (needs 2 alphanumeric + 9 digits = 11 chars after optional FR)
            'GR' => ['GR12345678', '12345678', 'GR1234567890', '1234567890'], // Wrong length (not 9)
            'HR' => ['HR1234567890', '1234567890', 'HR123456789012', '123456789012'], // Wrong length (not 11)
            'HU' => ['HU1234567', '1234567', 'HU123456789', '123456789'], // Wrong length (not 8)
            'IE' => ['IE123456', '123456', 'IE12345678A', '12345678A'], // Wrong format (not 1 digit + 1 alphanumeric + 5 digits + 1-2 letters)
            'IT' => ['IT1234567890', '1234567890', 'IT123456789012', '123456789012'], // Wrong length (not 11)
            'LT' => ['LT12345678', '12345678', 'LT12345678901', '12345678901'], // Wrong length (not 9 or 12)
            'LU' => ['LU1234567', '1234567', 'LU123456789', '123456789'], // Wrong length (not 8)
            'LV' => ['LV1234567890', '1234567890', 'LV123456789012', '123456789012'], // Wrong length (not 11)
            'MT' => ['MT1234567', '1234567', 'MT123456789', '123456789'], // Wrong length (not 8)
            'NL' => ['NL123456789', '123456789', 'NL123456789B', '123456789B'], // Missing B01 suffix
            'PL' => ['PL123456789', '123456789', 'PL12345678901', '12345678901'], // Wrong length (not 10)
            'PT' => ['PT12345678', '12345678', 'PT1234567890', '1234567890'], // Wrong length (not 9)
            'RO' => ['RO1', '1', 'RO12345678901', '12345678901'], // Too short (1 digit) or too long (11 digits, max is 10)
            'SE' => ['SE12345678900', '12345678900', 'SE1234567890012', '1234567890012'], // Wrong length (not 12)
            'SI' => ['SI1234567', '1234567', 'SI123456789', '123456789'], // Wrong length (not 8)
            'SK' => ['SK123456789', '123456789', 'SK12345678901', '12345678901'], // Wrong length (not 10)
        ];

        foreach ($invalidVatNumbers as $countryCode => $vatNumbers) {
            foreach ($vatNumbers as $vatNumber) {
                $scenario = [
                    'company_vat' => 'DE923356489',
                    'company_country' => 'DE',
                    'client_country' => $countryCode,
                    'client_vat' => $vatNumber,
                    'client_id_number' => '123456789',
                    'classification' => 'business',
                    'has_valid_vat' => true,
                    'over_threshold' => true,
                    'legal_entity_id' => 290868,
                    'is_tax_exempt' => false,
                ];

                $entity_data = $this->setupTestData($scenario);
                $client = $entity_data['client'];
                
                // Ensure client has required address fields
                $client->address1 = 'Test Address';
                $client->city = 'Test City';
                $client->postal_code = '12345';
                $client->save();

                $entityLevel = new EntityLevel();
                $result = $entityLevel->checkClient($client);

                $errorLabels = array_column($result['client'], 'label');
                $errorFields = array_column($result['client'], 'field');
                
                // Should have invalid_vat_number error (check both field and translated label)
                $hasInvalidVatError = in_array('vat_number', $errorFields) && 
                    in_array(ctrans('texts.invalid_vat_number'), $errorLabels);
                $this->assertTrue($hasInvalidVatError, 
                    "VAT number '{$vatNumber}' for country '{$countryCode}' should be invalid");
            }
        }
    }

    /**
     * Test that VAT number validation is skipped for individuals and government entities.
     */
    public function testVatNumberValidationSkippedForIndividualsAndGovernment()
    {
        $classifications = ['individual', 'government'];
        
        foreach ($classifications as $classification) {
            $scenario = [
                'company_vat' => 'DE923356489',
                'company_country' => 'DE',
                'client_country' => 'DE',
                'client_vat' => '', // Empty VAT number
                'client_id_number' => '123456789',
                'classification' => $classification,
                'has_valid_vat' => false,
                'over_threshold' => true,
                'legal_entity_id' => 290868,
                'is_tax_exempt' => false,
            ];

            $entity_data = $this->setupTestData($scenario);
            $client = $entity_data['client'];
            
            // Ensure client has required address fields
            $client->address1 = 'Test Address';
            $client->city = 'Test City';
            $client->postal_code = '12345';
            $client->save();

            $entityLevel = new EntityLevel();
            $result = $entityLevel->checkClient($client);

            $errorFields = array_column($result['client'], 'field');
            
            // Should not have vat_number error for individuals/government
            $this->assertNotContains('vat_number', $errorFields, 
                "VAT number should not be required for '{$classification}' classification");
        }
    }

    /**
     * Test that VAT number is required for business entities in EU countries.
     */
    public function testVatNumberRequiredForBusinessInEU()
    {
        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => '', // Empty VAT number
            'client_id_number' => '123456789',
            'classification' => 'business',
            'has_valid_vat' => false,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];

        $entity_data = $this->setupTestData($scenario);
        $client = $entity_data['client'];
        
        // Ensure client has required address fields
        $client->address1 = 'Test Address';
        $client->city = 'Test City';
        $client->postal_code = '12345';
        $client->save();

        $entityLevel = new EntityLevel();
        $result = $entityLevel->checkClient($client);

        $errorFields = array_column($result['client'], 'field');
        
        // Should have vat_number error for business in EU
        $this->assertContains('vat_number', $errorFields, 
            'VAT number should be required for business entities in EU countries');
    }

    /**
     * Test VAT number validation with special characters (spaces, dots, dashes).
     * These should be stripped before validation.
     */
    public function testVatNumberValidationWithSpecialCharacters()
    {
        $testCases = [
            ['DE', 'DE 123 456 789', true], // Valid with spaces
            ['DE', 'DE-123-456-789', true], // Valid with dashes
            ['DE', 'DE.123.456.789', true], // Valid with dots
            ['FR', 'FR AA 123 456 789', true], // Valid with spaces
            ['FR', 'FR-AA-123-456-789', true], // Valid with dashes
            ['NL', 'NL 123 456 789 B 01', true], // Valid with spaces
            ['NL', 'NL-123-456-789-B-01', true], // Valid with dashes
            ['DE', 'DE 123 456 78', false], // Invalid (wrong length) even with spaces
        ];

        foreach ($testCases as [$countryCode, $vatNumber, $shouldBeValid]) {
            $scenario = [
                'company_vat' => 'DE923356489',
                'company_country' => 'DE',
                'client_country' => $countryCode,
                'client_vat' => $vatNumber,
                'client_id_number' => '123456789',
                'classification' => 'business',
                'has_valid_vat' => true,
                'over_threshold' => true,
                'legal_entity_id' => 290868,
                'is_tax_exempt' => false,
            ];

            $entity_data = $this->setupTestData($scenario);
            $client = $entity_data['client'];
            
            // Ensure client has required address fields
            $client->address1 = 'Test Address';
            $client->city = 'Test City';
            $client->postal_code = '12345';
            $client->save();

            $entityLevel = new EntityLevel();
            $result = $entityLevel->checkClient($client);

            $errorLabels = array_column($result['client'], 'label');
            $errorFields = array_column($result['client'], 'field');
            
            $hasInvalidVatError = in_array('vat_number', $errorFields) && 
                in_array(ctrans('texts.invalid_vat_number'), $errorLabels);
            
            if ($shouldBeValid) {
                $this->assertFalse($hasInvalidVatError, 
                    "VAT number '{$vatNumber}' for country '{$countryCode}' should be valid after stripping special characters");
            } else {
                $this->assertTrue($hasInvalidVatError, 
                    "VAT number '{$vatNumber}' for country '{$countryCode}' should be invalid");
            }
        }
    }

    public function testEntityValidationFailsForClientViaInvoice()
    {
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

        $entity_data = $this->setupTestData($scenario);

        $invoice = $entity_data['invoice'];
        $client = $entity_data['client'];
        $client->address1 = '';
        $client->city = '';
        $client->save();

        $data = [
            'entity' => 'invoices',
            'entity_id' => $invoice->hashed_id
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/einvoice/validateEntity', $data);

        $response->assertStatus(422);

    }

    public function testEntityValidationFailsForCompany()
    {
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


        $entity_data = $this->setupTestData($scenario);

        $invoice = $entity_data['invoice'];
        $company = $entity_data['company'];
        $settings = $company->settings;

        $data = [
            'entity' => 'invoices',
            'entity_id' => $invoice->hashed_id
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/einvoice/validateEntity', $data);

        $response->assertStatus(422);

    }


    public function testEntityValidationShouldFailForFRBusiness()
    {
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


        $entity_data = $this->setupTestData($scenario);

        $invoice = $entity_data['invoice'];
        $company = $entity_data['company'];
        $settings = $company->settings;

        $settings->address1 = 'some address';
        $settings->city = 'some city';
        $settings->postal_code = '102394';

        $company->settings = $settings;
        $company->save();

        $data = [
            'entity' => 'invoices',
            'entity_id' => $invoice->hashed_id
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/einvoice/validateEntity', $data);

        if ($response->getStatusCode() !== 422) {

            $p = new Peppol($invoice);
            nlog($p->run()->toXml());
            nlog($invoice->withoutRelations()->toArray());
            nlog($response->json());
        }

        $response->assertStatus(422);

    }

    public function testEntityValidation()
    {
        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'FR',
            'client_vat' => 'FRAA123456789',
            'client_id_number' => '123456789',
            'classification' => 'government',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
        ];


        $entity_data = $this->setupTestData($scenario);

        $invoice = $entity_data['invoice'];

        $invoice->e_invoice = [
            'Invoice' => [
                'InvoicePeriod' => [
                    [
                    'cbc:StartDate' => $invoice->date,
                    'cbc:EndDate' => $invoice->due_date ?? $invoice->date,
                    'StartDate' => $invoice->date,
                    'EndDate' => $invoice->due_date ?? $invoice->date,
                    ]
                ]
            ]
        ];
        $invoice->save();

        $this->assertNotNull($invoice->e_invoice->Invoice->InvoicePeriod[0]->StartDate); //@phpstan-ignore-line

        $repo = new InvoiceRepository();
        $invoice = $repo->save([], $invoice);
        $invoice = $invoice->service()->markSent()->save();

        $company = $entity_data['company'];
        $settings = $company->settings;

        $settings->address1 = 'some address';
        $settings->city = 'some city';
        $settings->postal_code = '102394';

        $company->settings = $settings;
        $company->save();

        $data = [
            'entity' => 'invoices',
            'entity_id' => $invoice->hashed_id
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/einvoice/validateEntity', $data);

        if ($response->getStatusCode() !== 200) {

            $p = new Peppol($invoice);
            nlog($p->run()->toXml());
            nlog($invoice->withoutRelations()->toArray());
            nlog($response->json());
        }

        $response->assertStatus(200);

    }

    public function testWithChaosMonkey()
    {

        $scenarios = [
            [
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
            ],
            [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356482',
            'client_id_number' => '123456789',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
            ],
            [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356482',
            'client_id_number' => '123456789',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
            ],
            [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356482',
            'client_id_number' => '123456789',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
            ],
            [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'AT923356482',
            'client_id_number' => '123456789',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
            ],
            [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => '',
            'client_id_number' => '123456789',
            'classification' => 'individual',
            'has_valid_vat' => true,
            'over_threshold' => false,
            'legal_entity_id' => 290868,
            'is_tax_exempt' => false,
            ],
        ];

        foreach ($scenarios as $scenario) {
            $data = $this->setupTestData($scenario);

            $invoice = $data['invoice'];
            $invoice = $invoice->calc()->getInvoice();

            $repo = new InvoiceRepository();
            $invoice = $repo->save([], $invoice);

            $invoice->e_invoice = [
                'Invoice' => [
                    'InvoicePeriod' => [
                        [
                            'cbc:StartDate' => $invoice->date,
                            'cbc:EndDate' => $invoice->due_date ?? $invoice->date,
                            'StartDate' => $invoice->date,
                            'EndDate' => $invoice->due_date ?? $invoice->date,
                        ]
                    ]
                ]
            ];

            $invoice->save();

            $storecove = new Storecove();
            $p = new Peppol($invoice);
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
        }

        for ($x = 0; $x < $this->iterations; $x++) {

            $scenario = $scenarios[0];

            $data = $this->setupTestData($scenario);

            $invoice = $data['invoice'];
            $invoice = $invoice->calc()->getInvoice();

            $repo = new InvoiceRepository();
            $invoice = $repo->save([], $invoice);


            $invoice->e_invoice = [
                'Invoice' => [
                    'InvoicePeriod' => [
                        [
                            'cbc:StartDate' => $invoice->date,
                            'cbc:EndDate' => $invoice->due_date ?? $invoice->date,
                            'StartDate' => $invoice->date,
                            'EndDate' => $invoice->due_date ?? $invoice->date,
                        ]
                    ]
                ]
            ];

            $invoice->save();

            $storecove = new Storecove();
            $p = new Peppol($invoice);
            $p->run();

            try {
                $processor = new \Saxon\SaxonProcessor();
            } catch (\Throwable $e) {
                $this->markTestSkipped('saxon not installed');
            }

            $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($p->toXml());
            $validator->validate();

            if (count($validator->getErrors()) > 0) {
                nlog("index {$x}");
                nlog($invoice->calc()->getTotalTaxes());
                nlog($invoice->calc()->getTotal());
                nlog($invoice->calc()->getSubtotal());
                nlog($invoice->calc()->getTaxMap());
                nlog($invoice->withoutRelations()->toArray());
                nlog($p->toXml());
                nlog($validator->getErrors());
            }

            $this->assertCount(0, $validator->getErrors());

        }

        for ($x = 0; $x < $this->iterations; $x++) {

            $scenario = end($scenarios);

            $data = $this->setupTestData($scenario);

            $invoice = $data['invoice'];
            $invoice = $invoice->calc()->getInvoice();

            $repo = new InvoiceRepository();
            $invoice = $repo->save([], $invoice);

            $storecove = new Storecove();
            $p = new Peppol($invoice);
            $p->run();

            try {
                $processor = new \Saxon\SaxonProcessor();
            } catch (\Throwable $e) {
                $this->markTestSkipped('saxon not installed');
            }

            $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($p->toXml());
            $validator->validate();

            if (count($validator->getErrors()) > 0) {
                nlog("De-De tax");

                nlog("index {$x}");
                nlog($invoice->calc()->getTotalTaxes());
                nlog($invoice->calc()->getTotal());
                nlog($invoice->calc()->getSubtotal());
                nlog($invoice->calc()->getTaxMap());
                nlog($invoice->withoutRelations()->toArray());

                nlog($p->toXml());
                nlog($validator->getErrors());
            }

            $this->assertCount(0, $validator->getErrors());

        }

    }


    public function testDeInvoiceIntraCommunitySupply()
    {

        $settings = CompanySettings::defaults();
        $settings->address1 = 'Dudweilerstr. 34b';
        $settings->city = 'Ost Alessa';
        $settings->state = 'Bayern';
        $settings->postal_code = '98060';
        $settings->vat_number = 'DE923356489';
        $settings->country_id = '276';
        $settings->currency_id = '3';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new FinancialInstitutionBranch();
        $fib->ID = "DEUTDEMMXXX"; //BIC
        // $fib->Name = 'Deutsche Bank';

        $pfa = new PayeeFinancialAccount();
        $pfa->ID = 'DE89370400440532013000';
        $pfa->Name = 'PFA-NAME';
        // $pfa->AliasName = 'PFA-Alias';
        $pfa->AccountTypeCode = 'CHECKING';
        $pfa->AccountFormatCode = 'IBAN';
        $pfa->CurrencyCode = 'EUR';
        $pfa->FinancialInstitutionBranch = $fib;

        $pm = new PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;
        $einvoice->PaymentMeans[] = $pm;

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'e_invoice' => $stub,
        ]);

        $cu = CompanyUserFactory::create($this->user->id, $company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = false;
        $cu->save();

        $client_settings = ClientSettings::defaults();
        $client_settings->currency_id = '3';

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'German Client Name',
            'address1' => 'Kinderhausen 96b',
            'address2' => 'Apt. 842',
            'city' => 'Süd Jessestadt',
            'state' => 'Bayern',
            'postal_code' => '33323',
            'country_id' => 276,
            'routing_id' => 'ABC1234',
            'settings' => $client_settings,
        ]);


        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->discount = 0;
        $item->is_amount_discount = false;
        $item->tax_rate1 = 0;
        $item->tax_name1 = '';

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
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
            'is_amount_discount' => false,
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        $repo = new InvoiceRepository();
        $invoice = $repo->save([], $invoice);


        $this->assertEquals(100, $invoice->amount);

        $peppol = new Peppol($invoice);
        $peppol->setInvoiceDefaults();
        $peppol->run();


        nlog($peppol->toXml());

        // nlog($peppol->toObject());

        $de_invoice = $peppol->getDocument();

        $this->assertNotNull($de_invoice);

        $e = new EInvoice();
        $xml = $e->encode($de_invoice, 'xml');
        $this->assertNotNull($xml);


        $errors = $e->validate($de_invoice);

        if (count($errors) > 0) {
            nlog($errors);
        }

        $this->assertCount(0, $errors);

    }

    public function testDeInvoiceSingleInvoiceSurcharge()
    {

        $settings = CompanySettings::defaults();
        $settings->address1 = 'Dudweilerstr. 34b';
        $settings->city = 'Ost Alessa';
        $settings->state = 'Bayern';
        $settings->postal_code = '98060';
        $settings->vat_number = 'DE923356489';
        $settings->country_id = '276';
        $settings->currency_id = '3';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new FinancialInstitutionBranch();
        $fib->ID = "DEUTDEMMXXX"; //BIC
        // $fib->Name = 'Deutsche Bank';

        $pfa = new PayeeFinancialAccount();
        $pfa->ID = 'DE89370400440532013000';
        $pfa->Name = 'PFA-NAME';
        // $pfa->AliasName = 'PFA-Alias';
        $pfa->AccountTypeCode = 'CHECKING';
        $pfa->AccountFormatCode = 'IBAN';
        $pfa->CurrencyCode = 'EUR';
        $pfa->FinancialInstitutionBranch = $fib;

        $pm = new PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;
        $einvoice->PaymentMeans[] = $pm;

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $tax_data = new TaxModel();
        $tax_data->regions->EU->has_sales_above_threshold = true;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->seller_subregion = 'DE';

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'e_invoice' => $stub,
            'calculate_taxes' => true,
            'tax_data' => $tax_data,
            'legal_entity_id' => 290868,
        ]);

        $cu = CompanyUserFactory::create($this->user->id, $company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = false;
        $cu->save();

        $client_settings = ClientSettings::defaults();
        $client_settings->currency_id = '3';
        $client_settings->enable_e_invoice = true;

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'German Client Name',
            'address1' => 'Kinderhausen 96b',
            'address2' => 'Apt. 842',
            'city' => 'Süd Jessestadt',
            'state' => 'Bayern',
            'postal_code' => '33323',
            'country_id' => 276,
            'routing_id' => 'ABC1234',
            'settings' => $client_settings,
            'is_tax_exempt' => false,
        ]);


        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->discount = 0;
        $item->is_amount_discount = false;
        $item->tax_rate1 = 19;
        $item->tax_name1 = 'mwst';
        $item->tax_id = '1';

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'discount' => 0,
            'uses_inclusive_taxes' => false,
            'status_id' => 1,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
            'line_items' => [$item],
            'number' => 'DE-'.rand(1000, 100000),
            'date' => now()->format('Y-m-d'),
            'is_amount_discount' => false,
            'custom_surcharge1' => 10,
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $repo = new InvoiceRepository();
        $invoice = $repo->save([], $invoice);

        $invoice->service()->markSent()->save();


        nlog($invoice->toArray());

        $this->assertEquals(130.90, $invoice->amount);

        $peppol = new Peppol($invoice);
        $peppol->setInvoiceDefaults();
        $peppol->run();


        // $peppol->toJson()->toXml();

        // nlog($peppol->toObject());

        $de_invoice = $peppol->getDocument();

        $this->assertNotNull($de_invoice);

        $e = new EInvoice();
        $xml = $e->encode($de_invoice, 'xml');
        $this->assertNotNull($xml);


        $errors = $e->validate($de_invoice);

        if (count($errors) > 0) {
            nlog($errors);
        }

        $this->assertCount(0, $errors);

    }

    public function testDeInvoicePercentDiscounts()
    {

        $settings = CompanySettings::defaults();
        $settings->address1 = 'Dudweilerstr. 34b';
        $settings->city = 'Ost Alessa';
        $settings->state = 'Bayern';
        $settings->postal_code = '98060';
        $settings->vat_number = 'DE923356489';
        $settings->country_id = '276';
        $settings->currency_id = '3';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new FinancialInstitutionBranch();
        $fib->ID = "DEUTDEMMXXX"; //BIC
        // $fib->Name = 'Deutsche Bank';

        $pfa = new PayeeFinancialAccount();
        $pfa->ID = 'DE89370400440532013000';
        $pfa->Name = 'PFA-NAME';
        // $pfa->AliasName = 'PFA-Alias';
        $pfa->AccountTypeCode = 'CHECKING';
        $pfa->AccountFormatCode = 'IBAN';
        $pfa->CurrencyCode = 'EUR';
        $pfa->FinancialInstitutionBranch = $fib;

        $pm = new PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;
        $einvoice->PaymentMeans[] = $pm;

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'e_invoice' => $stub,
        ]);

        $cu = CompanyUserFactory::create($this->user->id, $company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = false;
        $cu->save();

        $client_settings = ClientSettings::defaults();
        $client_settings->currency_id = '3';

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'German Client Name',
            'address1' => 'Kinderhausen 96b',
            'address2' => 'Apt. 842',
            'city' => 'Süd Jessestadt',
            'state' => 'Bayern',
            'postal_code' => '33323',
            'country_id' => 276,
            'routing_id' => 'ABC1234',
            'settings' => $client_settings,
        ]);


        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->discount = 5;
        $item->is_amount_discount = false;
        $item->tax_rate1 = 19;
        $item->tax_name1 = 'mwst';

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
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
            'is_amount_discount' => false,
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $repo = new InvoiceRepository();
        $invoice = $repo->save([], $invoice);

        $invoice->service()->markSent()->save();

        $this->assertEquals(113.05, $invoice->amount);

        $peppol = new Peppol($invoice);
        $peppol->setInvoiceDefaults();
        $peppol->run();


        // $peppol->toJson()->toXml();

        // nlog($peppol->toObject());

        $de_invoice = $peppol->getDocument();

        $this->assertNotNull($de_invoice);

        $e = new EInvoice();
        $xml = $e->encode($de_invoice, 'xml');
        $this->assertNotNull($xml);


        $errors = $e->validate($de_invoice);

        if (count($errors) > 0) {
            nlog($errors);
        }

        $this->assertCount(0, $errors);

    }

    public function testDeInvoiceLevelAndItemLevelPercentageDiscount()
    {

        $settings = CompanySettings::defaults();
        $settings->address1 = 'Dudweilerstr. 34b';
        $settings->city = 'Ost Alessa';
        $settings->state = 'Bayern';
        $settings->postal_code = '98060';
        $settings->vat_number = 'DE923356489';
        $settings->id_number = '991-00110-12';
        $settings->country_id = '276';
        $settings->currency_id = '3';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new FinancialInstitutionBranch();
        $fib->ID = "DEUTDEMMXXX"; //BIC
        // $fib->Name = 'Deutsche Bank';

        $pfa = new PayeeFinancialAccount();
        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $id->value = 'DE89370400440532013000';
        $pfa->ID = $id;
        $pfa->Name = 'PFA-NAME';

        $pfa->FinancialInstitutionBranch = $fib;

        $pm = new PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;

        $pmc = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode();
        $pmc->value = '30';

        $pm->PaymentMeansCode = $pmc;

        $einvoice->PaymentMeans[] = $pm;

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'e_invoice' => $stub,
        ]);

        $cu = CompanyUserFactory::create($this->user->id, $company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = false;
        $cu->save();

        $client_settings = ClientSettings::defaults();
        $client_settings->currency_id = '3';

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'German Client Name',
            'address1' => 'Kinderhausen 96b',
            'address2' => 'Apt. 842',
            'city' => 'Süd Jessestadt',
            'state' => 'Bayern',
            'postal_code' => '33323',
            'country_id' => 276,
            'routing_id' => 'ABC1234',
            'settings' => $client_settings,
            'vat_number' => 'DE173655434',
        ]);

        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 100;
        $item->quantity = 1;
        $item->discount = 10;
        $item->is_amount_discount = false;
        $item->tax_rate1 = 19;
        $item->tax_name1 = 'mwst';

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'discount' => 10,
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
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'is_amount_discount' => false,
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $repo = new InvoiceRepository();
        $invoice = $repo->save([], $invoice);

        $invoice->service()->markSent()->save();

        $this->assertEquals(96.39, $invoice->amount);

        $peppol = new Peppol($invoice);
        $peppol->setInvoiceDefaults();
        $peppol->run();

        // nlog($peppol->toXml());

        $de_invoice = $peppol->getDocument();

        $this->assertNotNull($de_invoice);

        $e = new EInvoice();
        $xml = $e->encode($de_invoice, 'xml');


        $this->assertNotNull($xml);

        $errors = $e->validate($de_invoice);

        if (count($errors) > 0) {
            nlog($xml);
            nlog($errors);
        }

        $this->assertCount(0, $errors);

        $xml = $peppol->toXml();

        try {
            $processor = new \Saxon\SaxonProcessor();
        } catch (\Throwable $e) {
            $this->markTestSkipped('saxon not installed');
        }

        $validator = new XsltDocumentValidator($xml);
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }


    public function testDeInvoiceLevelPercentageDiscount()
    {

        $settings = CompanySettings::defaults();
        $settings->address1 = 'Dudweilerstr. 34b';
        $settings->city = 'Ost Alessa';
        $settings->state = 'Bayern';
        $settings->postal_code = '98060';
        $settings->vat_number = 'DE923356489';
        $settings->id_number = '991-00110-12';
        $settings->country_id = '276';
        $settings->currency_id = '3';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new FinancialInstitutionBranch();
        $fib->ID = "DEUTDEMMXXX"; //BIC
        // $fib->Name = 'Deutsche Bank';

        $pfa = new PayeeFinancialAccount();
        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $id->value = 'DE89370400440532013000';
        $pfa->ID = $id;
        $pfa->Name = 'PFA-NAME';

        $pfa->FinancialInstitutionBranch = $fib;

        $pm = new PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;

        $pmc = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode();
        $pmc->value = '30';

        $pm->PaymentMeansCode = $pmc;

        $einvoice->PaymentMeans[] = $pm;

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'e_invoice' => $stub,
        ]);

        $cu = CompanyUserFactory::create($this->user->id, $company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = false;
        $cu->save();

        $client_settings = ClientSettings::defaults();
        $client_settings->currency_id = '3';

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'German Client Name',
            'address1' => 'Kinderhausen 96b',
            'address2' => 'Apt. 842',
            'city' => 'Süd Jessestadt',
            'state' => 'Bayern',
            'postal_code' => '33323',
            'country_id' => 276,
            'routing_id' => 'ABC1234',
            'settings' => $client_settings,
            'vat_number' => 'DE173655434',
        ]);

        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 100;
        $item->quantity = 1;
        $item->discount = 0;
        $item->is_amount_discount = false;
        $item->tax_rate1 = 19;
        $item->tax_name1 = 'mwst';

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'discount' => 10,
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
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'is_amount_discount' => false,
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $repo = new InvoiceRepository();
        $invoice = $repo->save([], $invoice);

        $invoice->service()->markSent()->save();

        $this->assertEquals(107.10, $invoice->amount);

        $peppol = new Peppol($invoice);
        $peppol->setInvoiceDefaults();
        $peppol->run();

        // nlog($peppol->toXml());

        $de_invoice = $peppol->getDocument();

        $this->assertNotNull($de_invoice);

        $e = new EInvoice();
        $xml = $e->encode($de_invoice, 'xml');


        $this->assertNotNull($xml);

        $errors = $e->validate($de_invoice);

        if (count($errors) > 0) {
            nlog($xml);
            nlog($errors);
        }

        $this->assertCount(0, $errors);

        $xml = $peppol->toXml();

        try {
            $processor = new \Saxon\SaxonProcessor();
        } catch (\Throwable $e) {
            $this->markTestSkipped('saxon not installed');
        }

        $validator = new XsltDocumentValidator($xml);
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }

    public function testDeInvoiceAmountAndItemAmountDiscounts()
    {

        $settings = CompanySettings::defaults();
        $settings->address1 = 'Dudweilerstr. 34b';
        $settings->city = 'Ost Alessa';
        $settings->state = 'Bayern';
        $settings->postal_code = '98060';
        $settings->vat_number = 'DE923356489';
        $settings->id_number = '991-00110-12';
        $settings->country_id = '276';
        $settings->currency_id = '3';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new FinancialInstitutionBranch();
        $fib->ID = "DEUTDEMMXXX"; //BIC
        // $fib->Name = 'Deutsche Bank';

        $pfa = new PayeeFinancialAccount();
        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $id->value = 'DE89370400440532013000';
        $pfa->ID = $id;
        $pfa->Name = 'PFA-NAME';

        $pfa->FinancialInstitutionBranch = $fib;

        $pm = new PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;

        $pmc = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode();
        $pmc->value = '30';

        $pm->PaymentMeansCode = $pmc;

        $einvoice->PaymentMeans[] = $pm;

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'e_invoice' => $stub,
        ]);

        $cu = CompanyUserFactory::create($this->user->id, $company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = false;
        $cu->save();

        $client_settings = ClientSettings::defaults();
        $client_settings->currency_id = '3';

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'German Client Name',
            'address1' => 'Kinderhausen 96b',
            'address2' => 'Apt. 842',
            'city' => 'Süd Jessestadt',
            'state' => 'Bayern',
            'postal_code' => '33323',
            'country_id' => 276,
            'routing_id' => 'ABC1234',
            'settings' => $client_settings,
            'vat_number' => 'DE173655434',
        ]);

        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->discount = 5;
        $item->is_amount_discount = true;
        $item->tax_rate1 = 19;
        $item->tax_name1 = 'mwst';

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'discount' => 5,
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
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'is_amount_discount' => true,
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $repo = new InvoiceRepository();
        $invoice = $repo->save([], $invoice);


        $invoice->service()->markSent()->save();

        $this->assertEquals(107.1, $invoice->amount);

        $peppol = new Peppol($invoice);
        $peppol->setInvoiceDefaults();
        $peppol->run();

        $de_invoice = $peppol->getDocument();

        $this->assertNotNull($de_invoice);

        $e = new EInvoice();
        $xml = $e->encode($de_invoice, 'xml');
        $this->assertNotNull($xml);

        $errors = $e->validate($de_invoice);

        if (count($errors) > 0) {
            nlog($errors);
        }

        $this->assertCount(0, $errors);

        $xml = $peppol->toXml();



        try {
            $processor = new \Saxon\SaxonProcessor();
        } catch (\Throwable $e) {
            $this->markTestSkipped('saxon not installed');
        }

        $validator = new XsltDocumentValidator($xml);
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }

    public function testDeInvoiceAmountDiscounts()
    {

        $settings = CompanySettings::defaults();
        $settings->address1 = 'Dudweilerstr. 34b';
        $settings->city = 'Ost Alessa';
        $settings->state = 'Bayern';
        $settings->postal_code = '98060';
        $settings->vat_number = 'DE923356489';
        $settings->id_number = '991-00110-12';
        $settings->country_id = '276';
        $settings->currency_id = '3';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new FinancialInstitutionBranch();
        $fib->ID = "DEUTDEMMXXX"; //BIC
        // $fib->Name = 'Deutsche Bank';

        $pfa = new PayeeFinancialAccount();
        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $id->value = 'DE89370400440532013000';
        $pfa->ID = $id;
        $pfa->Name = 'PFA-NAME';

        $pfa->FinancialInstitutionBranch = $fib;

        $pm = new PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;

        $pmc = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode();
        $pmc->value = '30';

        $pm->PaymentMeansCode = $pmc;

        $einvoice->PaymentMeans[] = $pm;

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'e_invoice' => $stub,
        ]);

        $cu = CompanyUserFactory::create($this->user->id, $company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = false;
        $cu->save();

        $client_settings = ClientSettings::defaults();
        $client_settings->currency_id = '3';

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'German Client Name',
            'address1' => 'Kinderhausen 96b',
            'address2' => 'Apt. 842',
            'city' => 'Süd Jessestadt',
            'state' => 'Bayern',
            'postal_code' => '33323',
            'country_id' => 276,
            'routing_id' => 'ABC1234',
            'settings' => $client_settings,
            'vat_number' => 'DE173655434',
        ]);

        $item = new InvoiceItem();
        $item->product_key = "Product Key";
        $item->notes = "Product Description";
        $item->cost = 10;
        $item->quantity = 10;
        $item->discount = 5;
        $item->is_amount_discount = true;
        $item->tax_rate1 = 19;
        $item->tax_name1 = 'mwst';

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
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
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'is_amount_discount' => true,
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $repo = new InvoiceRepository();
        $invoice = $repo->save([], $invoice);

        $invoice->service()->markSent()->save();

        $this->assertEquals(113.05, $invoice->amount);

        $peppol = new Peppol($invoice);
        $peppol->setInvoiceDefaults();
        $peppol->run();

        $de_invoice = $peppol->getDocument();

        $this->assertNotNull($de_invoice);

        $e = new EInvoice();
        $xml = $e->encode($de_invoice, 'xml');
        $this->assertNotNull($xml);

        $errors = $e->validate($de_invoice);

        if (count($errors) > 0) {
            nlog($errors);
        }

        $this->assertCount(0, $errors);

        $xml = $peppol->toXml();



        try {
            $processor = new \Saxon\SaxonProcessor();
        } catch (\Throwable $e) {
            $this->markTestSkipped('saxon not installed');
        }

        $validator = new XsltDocumentValidator($xml);
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }


    public function testDeInvoice()
    {

        $settings = CompanySettings::defaults();
        $settings->address1 = 'Dudweilerstr. 34b';
        $settings->city = 'Ost Alessa';
        $settings->state = 'Bayern';
        $settings->postal_code = '98060';
        $settings->vat_number = 'DE923356489';
        $settings->country_id = '276';
        $settings->currency_id = '3';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new FinancialInstitutionBranch();
        $fib->ID = "DEUTDEMMXXX"; //BIC
        // $fib->Name = 'Deutsche Bank';

        $pfa = new PayeeFinancialAccount();
        $pfa->ID = 'DE89370400440532013000';
        $pfa->Name = 'PFA-NAME';
        // $pfa->AliasName = 'PFA-Alias';
        $pfa->AccountTypeCode = 'CHECKING';
        $pfa->AccountFormatCode = 'IBAN';
        $pfa->CurrencyCode = 'EUR';
        $pfa->FinancialInstitutionBranch = $fib;

        $pm = new PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;
        $einvoice->PaymentMeans[] = $pm;

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'e_invoice' => $stub,
        ]);

        $cu = CompanyUserFactory::create($this->user->id, $company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = false;
        $cu->save();

        $client_settings = ClientSettings::defaults();
        $client_settings->currency_id = '3';

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'German Client Name',
            'address1' => 'Kinderhausen 96b',
            'address2' => 'Apt. 842',
            'city' => 'Süd Jessestadt',
            'state' => 'Bayern',
            'postal_code' => '33323',
            'country_id' => 276,
            'routing_id' => 'ABC1234',
            'settings' => $client_settings,
            'vat_number' => 'DE173655434',
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
            'client_id' => $client->id,
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
            'date' => now()->format('Y-m-d')
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $repo = new InvoiceRepository();
        $invoice = $repo->save([], $invoice);

        $invoice->service()->markSent()->save();

        $this->assertEquals(119, $invoice->amount);

        $peppol = new Peppol($invoice);
        $peppol->setInvoiceDefaults();
        $peppol->run();


        $de_invoice = $peppol->getDocument();

        $this->assertNotNull($de_invoice);

        $e = new EInvoice();
        $xml = $e->encode($de_invoice, 'xml');
        $this->assertNotNull($xml);


        $errors = $e->validate($de_invoice);

        if (count($errors) > 0) {
            nlog($errors);
        }

        $this->assertCount(0, $errors);

    }


    public function testDeInvoiceInclusiveTaxes()
    {

        $settings = CompanySettings::defaults();
        $settings->address1 = 'Dudweilerstr. 34b';
        $settings->city = 'Ost Alessa';
        $settings->state = 'Bayern';
        $settings->postal_code = '98060';
        $settings->vat_number = 'DE923356489';
        $settings->country_id = '276';
        $settings->currency_id = '3';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new FinancialInstitutionBranch();
        $fib->ID = "DEUTDEMMXXX"; //BIC
        // $fib->Name = 'Deutsche Bank';

        $pfa = new PayeeFinancialAccount();
        $pfa->ID = 'DE89370400440532013000';
        $pfa->Name = 'PFA-NAME';
        // $pfa->AliasName = 'PFA-Alias';
        $pfa->AccountTypeCode = 'CHECKING';
        $pfa->AccountFormatCode = 'IBAN';
        $pfa->CurrencyCode = 'EUR';
        $pfa->FinancialInstitutionBranch = $fib;

        $pm = new PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;
        $einvoice->PaymentMeans[] = $pm;


        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'e_invoice' => $stub,
        ]);

        $cu = CompanyUserFactory::create($this->user->id, $company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = false;
        $cu->save();

        $client_settings = ClientSettings::defaults();
        $client_settings->currency_id = '3';

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'German Client Name',
            'address1' => 'Kinderhausen 96b',
            'address2' => 'Apt. 842',
            'city' => 'Süd Jessestadt',
            'state' => 'Bayern',
            'postal_code' => '33323',
            'country_id' => 276,
            'routing_id' => 'ABC1234',
            'settings' => $client_settings,
            'vat_number' => 'DE173655434',
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
            'client_id' => $client->id,
            'discount' => 0,
            'uses_inclusive_taxes' => true,
            'status_id' => 1,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name2' => '',
            'tax_name3' => '',
            'line_items' => [$item],
            'number' => 'DE-'.rand(1000, 100000),
            'date' => now()->format('Y-m-d')
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $repo = new InvoiceRepository();
        $invoice = $repo->save([], $invoice);

        $invoice->service()->markSent()->save();

        $this->assertEquals(100, $invoice->amount);

        $peppol = new Peppol($invoice);
        $peppol->setInvoiceDefaults();
        $peppol->run();

        $de_invoice = $peppol->getDocument();

        $this->assertNotNull($de_invoice);

        $e = new EInvoice();
        $xml = $e->encode($de_invoice, 'xml');
        $this->assertNotNull($xml);

        $errors = $e->validate($de_invoice);

        if (count($errors) > 0) {
            nlog($errors);
        }

        $this->assertCount(0, $errors);

    }


    public function testInvoiceBoot()
    {

        $settings = CompanySettings::defaults();
        $settings->address1 = 'Via Silvio Spaventa 108';
        $settings->city = 'Calcinelli';

        $settings->state = 'PA';

        // $settings->state = 'Perugia';
        $settings->postal_code = '61030';
        $settings->country_id = '380';
        $settings->currency_id = '3';
        $settings->vat_number = '01234567890';
        $settings->id_number = '';

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);


        $cu = CompanyUserFactory::create($this->user->id, $company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = false;
        $cu->save();

        $client_settings = ClientSettings::defaults();
        $client_settings->currency_id = '3';

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'Italian Client Name',
            'address1' => 'Via Antonio da Legnago 68',
            'city' => 'Monasterace',
            'state' => 'CR',
            // 'state' => 'Reggio Calabria',
            'postal_code' => '89040',
            'country_id' => 380,
            'routing_id' => 'ABC1234',
            'settings' => $client_settings,
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
            'client_id' => $client->id,
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
            'number' => 'ITA-'.rand(1000, 100000),
            'date' => now()->format('Y-m-d')
        ]);

        $repo = new InvoiceRepository();
        $invoice = $repo->save([], $invoice);


        $invoice->service()->markSent()->save();

        $peppol = new Peppol($invoice);
        $peppol->run();

        $fe = $peppol->getDocument();

        $this->assertNotNull($fe);

        $this->assertInstanceOf(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $fe);

        $e = new EInvoice();
        $xml = $e->encode($fe, 'xml');
        $this->assertNotNull($xml);

        $json = $e->encode($fe, 'json');
        $this->assertNotNull($json);


        $decode = $e->decode('Peppol', $json, 'json');

        $this->assertInstanceOf(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $decode);

        $errors = $e->validate($fe);

        if (count($errors) > 0) {
            nlog($errors);
        }

        $this->assertCount(0, $errors);

    }

}
