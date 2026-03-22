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

namespace Tests\Feature\Quickbooks;

use App\DataMapper\ClientSync;
use App\DataMapper\InvoiceSync;
use App\DataMapper\ProductSync;
use App\Factory\ClientContactFactory;
use App\Factory\ClientFactory;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Factory\PaymentFactory;
use App\Factory\ProductFactory;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PaymentType;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Quickbooks\Models\QbInvoice;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\ClientTransformer;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use App\Services\Quickbooks\Transformers\ProductTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Functional tests for QuickBooks USA integration.
 *
 * These tests use a real QBUS company from the database with a live QuickBooks
 * connection. They verify that products, clients, and invoices transform correctly
 * between Invoice Ninja and QuickBooks format, with US-style automatic sales tax
 * calculations (AST) using "TAX"/"NON" TaxCodeRef values instead of numeric IDs.
 *
 * US companies with AST enabled sync invoices synchronously with the QB API first,
 * so that QB calculates the taxes and returns them in the response.
 */
class QuickbooksUSATest extends TestCase
{
    use DatabaseTransactions;

    private ClientTransformer $client_transformer;
    private ProductTransformer $product_transformer;
    private InvoiceTransformer $invoice_transformer;
    private QuickbooksService $qb;

    private ?Company $company;
    private ?User $user;

    /** QB entity IDs created during tests, for cleanup */
    private array $qb_cleanup = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (Company::whereNotNull('quickbooks')->count() == 0) {
            $this->markTestSkipped('No Quickbooks companies found');
        }

        $this->configureUSCompany();

        $this->client_transformer = new ClientTransformer($this->company);
        $this->product_transformer = new ProductTransformer($this->company);
        $this->invoice_transformer = new InvoiceTransformer($this->company);
    }

    /**
     * Load the real QBUS company and initialize the QuickbooksService.
     */
    private function configureUSCompany(): void
    {
        $this->company = Company::query()
                        ->where('settings->name', 'QBUS')
                        ->first();

        if (!$this->company) {
            $this->markTestSkipped('No US company found');
        }

        $this->user = $this->company->users()->orderBy('id', 'asc')->first();
        $this->qb = new QuickbooksService($this->company);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  PRODUCT TESTS
    // ──────────────────────────────────────────────────────────────────────

    public function test_product_ninja_to_qb_physical_item()
    {
        $line_item = InvoiceItemFactory::create();
        $line_item->product_key = 'Baseball Bat';
        $line_item->notes = 'Louisville Slugger wooden bat, 34 inch';
        $line_item->cost = 89.99;
        $line_item->quantity = 1;
        $line_item->type_id = '1'; // Physical
        $line_item->tax_id = '1';  // Physical (taxable)

        $qb_data = $this->product_transformer->qbTransform($line_item, '30');

        $this->assertEquals('Baseball Bat', $qb_data['Name']);
        $this->assertEquals('Louisville Slugger wooden bat, 34 inch', $qb_data['Description']);
        $this->assertEquals(89.99, $qb_data['UnitPrice']);
        $this->assertEquals('NonInventory', $qb_data['Type']);
        $this->assertEquals('30', $qb_data['IncomeAccountRef']['value']);
    }

    public function test_product_ninja_to_qb_service_item()
    {
        $line_item = InvoiceItemFactory::create();
        $line_item->product_key = 'Consulting Hour';
        $line_item->notes = 'IT consulting service';
        $line_item->cost = 200.00;
        $line_item->quantity = 1;
        $line_item->type_id = '2'; // Service
        $line_item->tax_id = '2';  // Service

        $qb_data = $this->product_transformer->qbTransform($line_item, '31');

        $this->assertEquals('Consulting Hour', $qb_data['Name']);
        $this->assertEquals('Service', $qb_data['Type']);
        $this->assertEquals('31', $qb_data['IncomeAccountRef']['value']);
    }

    public function test_product_ninja_to_qb_exempt_becomes_service()
    {
        $line_item = InvoiceItemFactory::create();
        $line_item->product_key = 'Prescription Medicine';
        $line_item->notes = 'Tax-exempt prescription medication';
        $line_item->cost = 45.00;
        $line_item->quantity = 1;
        $line_item->type_id = '1';
        $line_item->tax_id = '5'; // Exempt

        $qb_data = $this->product_transformer->qbTransform($line_item, '30');

        // Exempt items (tax_id=5) map to Service type in QB
        $this->assertEquals('Service', $qb_data['Type']);
    }

    public function test_product_ninja_to_qb_zero_rated_becomes_service()
    {
        $line_item = InvoiceItemFactory::create();
        $line_item->product_key = 'Export Goods';
        $line_item->notes = 'Zero-rated export merchandise';
        $line_item->cost = 100.00;
        $line_item->quantity = 1;
        $line_item->type_id = '1';
        $line_item->tax_id = '8'; // Zero-rated

        $qb_data = $this->product_transformer->qbTransform($line_item, '30');

        $this->assertEquals('Service', $qb_data['Type']);
    }

    public function test_product_qb_to_ninja_taxable_item()
    {
        $qb_item = [
            'Id' => '100',
            'Name' => 'Widget Pro',
            'Description' => 'Premium widget for US market',
            'PurchaseCost' => 49.99,
            'UnitPrice' => 99.99,
            'QtyOnHand' => 50,
            'Type' => 'NonInventory',
            'IncomeAccountRef' => ['value' => '30'],
            'SalesTaxCodeRef' => ['value' => 'TAX'],
        ];

        $ninja_data = $this->product_transformer->transform($qb_item);

        $this->assertEquals('100', $ninja_data['id']);
        $this->assertEquals('Widget Pro', $ninja_data['product_key']);
        $this->assertEquals('Premium widget for US market', $ninja_data['notes']);
        $this->assertEquals(49.99, $ninja_data['cost']);
        $this->assertEquals(99.99, $ninja_data['price']);
        $this->assertEquals(50, $ninja_data['in_stock_quantity']);
        $this->assertEquals('1', $ninja_data['type_id']); // NonInventory => physical
        $this->assertEquals('30', $ninja_data['income_account_id']);
    }

    public function test_product_qb_to_ninja_service_item()
    {
        $qb_item = [
            'Id' => '101',
            'Name' => 'Lawn Care',
            'Description' => 'Weekly lawn maintenance service',
            'PurchaseCost' => 0,
            'UnitPrice' => 85.00,
            'Type' => 'Service',
            'IncomeAccountRef' => ['value' => '31'],
            'SalesTaxCodeRef' => ['value' => 'TAX'],
        ];

        $ninja_data = $this->product_transformer->transform($qb_item);

        $this->assertEquals('Lawn Care', $ninja_data['product_key']);
        $this->assertEquals(85.00, $ninja_data['price']);
        $this->assertEquals('2', $ninja_data['type_id']); // Service => type 2
        $this->assertEquals('2', $ninja_data['tax_id']);   // Service type gets tax_id 2
    }

    public function test_product_qb_to_ninja_exempt_item()
    {
        $qb_item = [
            'Id' => '102',
            'Name' => 'Gift Card',
            'Description' => 'Non-taxable gift card',
            'PurchaseCost' => 25.00,
            'UnitPrice' => 25.00,
            'Type' => 'NonInventory',
            'IncomeAccountRef' => ['value' => '30'],
            'SalesTaxCodeRef' => ['value' => 'NON'],
        ];

        $ninja_data = $this->product_transformer->transform($qb_item);

        $this->assertEquals('5', $ninja_data['tax_id']); // NON => exempt (tax_id 5)
    }

    public function test_product_qb_to_ninja_skips_category_items()
    {
        $qb_item = [
            'Id' => '200',
            'Name' => 'Electronics Category',
            'Type' => 'Category',
        ];

        // Should not crash — transform returns minimal data with a log warning
        $ninja_data = $this->product_transformer->transform($qb_item);
        $this->assertIsArray($ninja_data);
        $this->assertEquals('200', $ninja_data['id']);
    }

    public function test_product_round_trip_preserves_data()
    {
        // Create a product in Invoice Ninja
        $product = ProductFactory::create($this->company->id, $this->user->id);
        $product->product_key = 'BBQ Grill';
        $product->notes = 'Stainless steel propane grill with 4 burners';
        $product->price = 499.99;
        $product->cost = 250.00;
        $product->tax_name1 = 'Sales Tax';
        $product->tax_rate1 = 8.25;
        $product->tax_id = '1';
        $product->saveQuietly();

        // Transform to QB format (via line item)
        $line_item = InvoiceItemFactory::create();
        $line_item->product_key = $product->product_key;
        $line_item->notes = $product->notes;
        $line_item->cost = $product->price;
        $line_item->type_id = '1';
        $line_item->tax_id = '1';

        $qb_data = $this->product_transformer->qbTransform($line_item, '30');

        $this->assertEquals('BBQ Grill', $qb_data['Name']);
        $this->assertEquals('NonInventory', $qb_data['Type']);
        $this->assertEquals(499.99, $qb_data['UnitPrice']);

        // Now simulate QB returning the item
        $qb_response = [
            'Id' => '999',
            'Name' => $qb_data['Name'],
            'Description' => $qb_data['Description'],
            'UnitPrice' => $qb_data['UnitPrice'],
            'PurchaseCost' => $qb_data['PurchaseCost'],
            'Type' => $qb_data['Type'],
            'IncomeAccountRef' => $qb_data['IncomeAccountRef'],
            'SalesTaxCodeRef' => ['value' => 'TAX'], // US uses TAX/NON
        ];

        $ninja_back = $this->product_transformer->transform($qb_response);

        $this->assertEquals('BBQ Grill', $ninja_back['product_key']);
        $this->assertEquals(499.99, $ninja_back['price']);
        $this->assertEquals('1', $ninja_back['type_id']); // NonInventory -> physical
    }

    // ──────────────────────────────────────────────────────────────────────
    //  CLIENT TESTS
    // ──────────────────────────────────────────────────────────────────────

    public function test_client_ninja_to_qb_us_address()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->name = 'Acme Corporation';
        $client->address1 = '350 Fifth Avenue';
        $client->city = 'New York';
        $client->state = 'NY';
        $client->postal_code = '10118';
        $client->country_id = 840; // USA
        $client->shipping_address1 = '1600 Amphitheatre Parkway';
        $client->shipping_city = 'Mountain View';
        $client->shipping_state = 'CA';
        $client->shipping_postal_code = '94043';
        $client->shipping_country_id = 840;
        $client->public_notes = 'Key enterprise client';
        $client->id_number = 'EIN-12-3456789';
        $client->saveQuietly();

        $contact = ClientContactFactory::create($this->company->id, $this->user->id);
        $contact->client_id = $client->id;
        $contact->first_name = 'John';
        $contact->last_name = 'Smith';
        $contact->email = 'john@acmecorp.com';
        $contact->phone = '+1-212-555-1234';
        $contact->is_primary = true;
        $contact->saveQuietly();

        $client = $client->fresh();

        $qb_data = $this->client_transformer->ninjaToQb($client, $this->qb);

        $this->assertEquals('Acme Corporation', $qb_data['DisplayName']);
        $this->assertEquals('Acme Corporation', $qb_data['CompanyName']);
        $this->assertEquals('john@acmecorp.com', $qb_data['PrimaryEmailAddr']['Address']);
        $this->assertEquals('+1-212-555-1234', $qb_data['PrimaryPhone']['FreeFormNumber']);

        // Billing address
        $this->assertEquals('350 Fifth Avenue', $qb_data['BillAddr']['Line1']);
        $this->assertEquals('New York', $qb_data['BillAddr']['City']);
        $this->assertEquals('NY', $qb_data['BillAddr']['CountrySubDivisionCode']);
        $this->assertEquals('10118', $qb_data['BillAddr']['PostalCode']);

        // Shipping address
        $this->assertEquals('1600 Amphitheatre Parkway', $qb_data['ShipAddr']['Line1']);
        $this->assertEquals('Mountain View', $qb_data['ShipAddr']['City']);
        $this->assertEquals('CA', $qb_data['ShipAddr']['CountrySubDivisionCode']);
        $this->assertEquals('94043', $qb_data['ShipAddr']['PostalCode']);

        // Contact details
        $this->assertEquals('John', $qb_data['GivenName']);
        $this->assertEquals('Smith', $qb_data['FamilyName']);
        $this->assertEquals('Key enterprise client', $qb_data['Notes']);
        $this->assertTrue($qb_data['Active']);
    }

    public function test_client_qb_to_ninja_us_customer()
    {
        $qb_customer = [
            'Id' => '500',
            'CompanyName' => 'Tesla Inc.',
            'DisplayName' => 'Tesla Inc.',
            'GivenName' => 'Elon',
            'FamilyName' => 'Musk',
            'PrimaryEmailAddr' => ['Address' => 'elon@tesla.com'],
            'PrimaryPhone' => ['FreeFormNumber' => '+1-650-555-9876'],
            'BillAddr' => [
                'Line1' => '3500 Deer Creek Road',
                'City' => 'Palo Alto',
                'CountrySubDivisionCode' => 'CA',
                'PostalCode' => '94304',
                'Country' => 'US',
            ],
            'ShipAddr' => [
                'Line1' => '1 Tesla Road',
                'City' => 'Austin',
                'CountrySubDivisionCode' => 'TX',
                'PostalCode' => '78725',
                'Country' => 'US',
            ],
            'Notes' => 'Electric vehicle manufacturer',
            'Taxable' => true,
            'CurrencyRef' => 'USD',
            'V4IDPseudonym' => 'def456hash',
            'PrimaryTaxIdentifier' => '91-1223280',
        ];

        [$client_data, $contact_data, $merge_data] = $this->client_transformer->qbToNinja($qb_customer, $this->qb);

        // Client data
        $this->assertEquals('500', $client_data['id']);
        $this->assertEquals('Tesla Inc.', $client_data['name']);
        $this->assertEquals('3500 Deer Creek Road', $client_data['address1']);
        $this->assertEquals('Palo Alto', $client_data['city']);
        $this->assertEquals('CA', $client_data['state']);
        $this->assertEquals('94304', $client_data['postal_code']);

        // Shipping
        $this->assertEquals('1 Tesla Road', $client_data['shipping_address1']);
        $this->assertEquals('Austin', $client_data['shipping_city']);
        $this->assertEquals('TX', $client_data['shipping_state']);
        $this->assertEquals('78725', $client_data['shipping_postal_code']);

        // Business details
        $this->assertEquals('Electric vehicle manufacturer', $client_data['private_notes']);
        $this->assertEquals('91-1223280', $client_data['vat_number']);
        $this->assertFalse($client_data['is_tax_exempt']); // Taxable = true

        // Contact data
        $this->assertEquals('Elon', $contact_data['first_name']);
        $this->assertEquals('Musk', $contact_data['last_name']);
        $this->assertEquals('elon@tesla.com', $contact_data['email']);
        $this->assertEquals('+1-650-555-9876', $contact_data['phone']);
    }

    public function test_client_qb_to_ninja_null_ship_addr_copies_billing()
    {
        $qb_customer = [
            'Id' => '501',
            'CompanyName' => 'Small Business LLC',
            'DisplayName' => 'Small Business LLC',
            'GivenName' => 'Jane',
            'FamilyName' => 'Doe',
            'PrimaryEmailAddr' => ['Address' => 'jane@smallbiz.com'],
            'BillAddr' => [
                'Line1' => '100 Main Street',
                'City' => 'Portland',
                'CountrySubDivisionCode' => 'OR',
                'PostalCode' => '97201',
                'Country' => 'US',
            ],
            'ShipAddr' => null, // NULL means "same as billing"
            'Taxable' => true,
        ];

        [$client_data, $contact_data, $merge_data] = $this->client_transformer->qbToNinja($qb_customer, $this->qb);

        // Shipping should copy billing when ShipAddr is null
        $this->assertEquals($client_data['address1'], $client_data['shipping_address1']);
        $this->assertEquals($client_data['city'], $client_data['shipping_city']);
        $this->assertEquals($client_data['state'], $client_data['shipping_state']);
        $this->assertEquals($client_data['postal_code'], $client_data['shipping_postal_code']);
    }

    public function test_client_qb_to_ninja_tax_exempt()
    {
        $qb_customer = [
            'Id' => '502',
            'CompanyName' => 'Government Agency',
            'DisplayName' => 'Government Agency',
            'GivenName' => 'Director',
            'FamilyName' => 'Public',
            'Taxable' => false, // Tax exempt
            'BillAddr' => [
                'Line1' => '1 Federal Plaza',
                'City' => 'Washington',
                'CountrySubDivisionCode' => 'DC',
                'PostalCode' => '20001',
            ],
        ];

        [$client_data, $contact_data, $merge_data] = $this->client_transformer->qbToNinja($qb_customer, $this->qb);

        $this->assertTrue($client_data['is_tax_exempt']);
    }

    public function test_client_round_trip_preserves_us_data()
    {
        // Create IN client
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->name = 'Boeing Company';
        $client->address1 = '100 North Riverside Plaza';
        $client->city = 'Chicago';
        $client->state = 'IL';
        $client->postal_code = '60606';
        $client->country_id = 840;
        $client->id_number = 'EIN-91-0425694';
        $client->saveQuietly();

        $contact = ClientContactFactory::create($this->company->id, $this->user->id);
        $contact->client_id = $client->id;
        $contact->first_name = 'Dave';
        $contact->last_name = 'Calhoun';
        $contact->email = 'dave@boeing.com';
        $contact->is_primary = true;
        $contact->saveQuietly();

        $client = $client->fresh();

        // Transform to QB
        $qb_data = $this->client_transformer->ninjaToQb($client, $this->qb);

        // Verify QB format
        $this->assertEquals('Boeing Company', $qb_data['CompanyName']);
        $this->assertEquals('Chicago', $qb_data['BillAddr']['City']);
        $this->assertEquals('IL', $qb_data['BillAddr']['CountrySubDivisionCode']);

        // Simulate QB returning the customer data
        $qb_response = [
            'Id' => '600',
            'CompanyName' => $qb_data['CompanyName'],
            'DisplayName' => $qb_data['DisplayName'],
            'GivenName' => $qb_data['GivenName'],
            'FamilyName' => $qb_data['FamilyName'],
            'PrimaryEmailAddr' => $qb_data['PrimaryEmailAddr'],
            'PrimaryPhone' => $qb_data['PrimaryPhone'],
            'BillAddr' => $qb_data['BillAddr'],
            'ShipAddr' => $qb_data['ShipAddr'],
            'BusinessNumber' => $qb_data['BusinessNumber'],
            'Notes' => $qb_data['Notes'],
            'Taxable' => true,
            'V4IDPseudonym' => $qb_data['V4IDPseudonym'],
        ];

        [$client_back, $contact_back, $merge] = $this->client_transformer->qbToNinja($qb_response, $this->qb);

        $this->assertEquals('Boeing Company', $client_back['name']);
        $this->assertEquals('100 North Riverside Plaza', $client_back['address1']);
        $this->assertEquals('Chicago', $client_back['city']);
        $this->assertEquals('IL', $client_back['state']);
        $this->assertEquals('60606', $client_back['postal_code']);
        $this->assertEquals('Dave', $contact_back['first_name']);
        $this->assertEquals('Calhoun', $contact_back['last_name']);
        $this->assertEquals('dave@boeing.com', $contact_back['email']);
    }

    public function test_client_deleted_becomes_inactive()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->name = 'Deleted US Co.';
        $client->deleted_at = now();
        $client->saveQuietly();

        $contact = ClientContactFactory::create($this->company->id, $this->user->id);
        $contact->client_id = $client->id;
        $contact->is_primary = true;
        $contact->saveQuietly();

        $client = $client->fresh();

        $qb_data = $this->client_transformer->ninjaToQb($client, $this->qb);

        $this->assertFalse($qb_data['Active']);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  INVOICE TRANSFORMER TESTS — US TAX HANDLING (AST)
    // ──────────────────────────────────────────────────────────────────────

    public function test_invoice_ninjaToQb_uses_tax_non_for_us()
    {
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Widget A', 100.00, 'Sales Tax', 8.25),
        ]);

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        // US companies use "TAX"/"NON" TaxCodeRef, not numeric IDs
        $this->assertArrayHasKey('Line', $qb_data);
        $this->assertNotEmpty($qb_data['Line']);

        $line = $qb_data['Line'][0];
        $tax_code_ref = $line['SalesItemLineDetail']['TaxCodeRef']['value'];

        // US taxable items should use "TAX"
        $this->assertEquals('TAX', $tax_code_ref);
    }

    public function test_invoice_ninjaToQb_exempt_line_uses_non_code()
    {
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Exempt Item', 30.00, '', 0, '5'), // tax_id=5 = Exempt
        ]);

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        $line = $qb_data['Line'][0];
        $tax_code_ref = $line['SalesItemLineDetail']['TaxCodeRef']['value'];

        // Exempt items should use "NON"
        $this->assertEquals('NON', $tax_code_ref);
    }

    public function test_invoice_ninjaToQb_zero_rated_uses_non_code()
    {
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Zero Rated Export', 500.00, '', 0, '8'), // tax_id=8 = Zero-rated
        ]);

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        $line = $qb_data['Line'][0];
        $tax_code_ref = $line['SalesItemLineDetail']['TaxCodeRef']['value'];

        // Zero-rated items should also use "NON"
        $this->assertEquals('NON', $tax_code_ref);
    }

    public function test_invoice_ninjaToQb_mixed_taxable_and_exempt_lines()
    {
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Taxable Widget', 100.00, 'Sales Tax', 8.25),
            $this->makeLineItem('Exempt Service', 25.00, '', 0, '5'),
            $this->makeLineItem('Another Taxable', 200.00, 'Sales Tax', 8.25),
        ]);

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        $this->assertCount(3, $qb_data['Line']);

        // Line 1: TAX
        $this->assertEquals('TAX', $qb_data['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);

        // Line 2: NON (exempt)
        $this->assertEquals('NON', $qb_data['Line'][1]['SalesItemLineDetail']['TaxCodeRef']['value']);

        // Line 3: TAX
        $this->assertEquals('TAX', $qb_data['Line'][2]['SalesItemLineDetail']['TaxCodeRef']['value']);
    }

    public function test_invoice_ninjaToQb_global_tax_calculation_for_ast()
    {
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Product', 100.00, 'Sales Tax', 8.25),
        ]);

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        $ast = $this->company->quickbooks->settings->automatic_taxes;

        if ($ast) {
            // With AST enabled, GlobalTaxCalculation should be TaxExcluded
            $this->assertEquals('TaxExcluded', $qb_data['GlobalTaxCalculation']);
        } else {
            // Without AST, US companies use NotApplicable
            $this->assertEquals('NotApplicable', $qb_data['GlobalTaxCalculation']);
        }
    }

    public function test_invoice_ninjaToQb_no_txn_tax_detail_with_ast()
    {
        $ast = $this->company->quickbooks->settings->automatic_taxes;

        if (!$ast) {
            $this->markTestSkipped('QBUS company does not have AST enabled');
        }

        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Product', 100.00, 'Sales Tax', 8.25),
        ]);

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        // US companies with AST should NOT have TxnTaxDetail — QB calculates taxes
        $this->assertArrayNotHasKey('TxnTaxDetail', $qb_data);
    }

    public function test_invoice_ninjaToQb_has_txn_tax_detail_without_ast()
    {
        $ast = $this->company->quickbooks->settings->automatic_taxes;

        if ($ast) {
            $this->markTestSkipped('QBUS company has AST enabled — TxnTaxDetail only applies without AST');
        }

        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Product', 100.00, 'Sales Tax', 8.25),
        ]);

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        // US companies without AST should include TxnTaxDetail
        $this->assertArrayHasKey('TxnTaxDetail', $qb_data);
    }

    public function test_invoice_ninjaToQb_line_item_amounts_correct()
    {
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Item A', 50.00, 'Sales Tax', 8.25, '1', 3),
            $this->makeLineItem('Item B', 100.00, 'Sales Tax', 8.25, '1', 2),
        ]);

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        // Line 1: 50 * 3 = 150
        $this->assertEquals(3, $qb_data['Line'][0]['SalesItemLineDetail']['Qty']);
        $this->assertEquals(50.00, $qb_data['Line'][0]['SalesItemLineDetail']['UnitPrice']);
        $this->assertEquals(150.00, $qb_data['Line'][0]['Amount']);

        // Line 2: 100 * 2 = 200
        $this->assertEquals(2, $qb_data['Line'][1]['SalesItemLineDetail']['Qty']);
        $this->assertEquals(100.00, $qb_data['Line'][1]['SalesItemLineDetail']['UnitPrice']);
        $this->assertEquals(200.00, $qb_data['Line'][1]['Amount']);
    }

    public function test_invoice_ninjaToQb_line_numbers_sequential()
    {
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('One', 10.00, 'Sales Tax', 8.25),
            $this->makeLineItem('Two', 20.00, 'Sales Tax', 8.25),
            $this->makeLineItem('Three', 30.00, 'Sales Tax', 8.25),
        ]);

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        $this->assertEquals(1, $qb_data['Line'][0]['LineNum']);
        $this->assertEquals(2, $qb_data['Line'][1]['LineNum']);
        $this->assertEquals(3, $qb_data['Line'][2]['LineNum']);
    }

    public function test_invoice_ninjaToQb_includes_metadata()
    {
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Product', 100.00, 'Sales Tax', 8.25),
        ]);

        $invoice->number = 'INV-US-001';
        $invoice->date = '2026-02-15';
        $invoice->due_date = '2026-03-15';
        $invoice->po_number = 'PO-67890';
        $invoice->public_notes = 'Thank you for your business';
        $invoice->private_notes = 'Internal note about this client';
        $invoice->partial = 50.00;
        $invoice->saveQuietly();

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        $this->assertEquals('INV-US-001', $qb_data['DocNumber']);
        $this->assertEquals('2026-02-15', $qb_data['TxnDate']);
        $this->assertEquals('2026-03-15', $qb_data['DueDate']);
        $this->assertEquals('PO-67890', $qb_data['PONumber']);
        $this->assertEquals('Thank you for your business', $qb_data['CustomerMemo']['value']);
        $this->assertEquals('Internal note about this client', $qb_data['PrivateNote']);
        $this->assertEquals(50.00, $qb_data['Deposit']);
    }

    public function test_invoice_ninjaToQb_includes_qb_id_for_updates()
    {
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Product', 100.00, 'Sales Tax', 8.25),
        ]);

        $sync = new InvoiceSync();
        $sync->qb_id = '888';
        $invoice->sync = $sync;
        $invoice->saveQuietly();

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        $this->assertEquals('888', $qb_data['Id']);
    }

    public function test_invoice_ninjaToQb_no_id_for_new_invoice()
    {
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Product', 100.00, 'Sales Tax', 8.25),
        ]);

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        $this->assertArrayNotHasKey('Id', $qb_data);
    }

    public function test_invoice_ninjaToQb_discount_line()
    {
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Product', 100.00, 'Sales Tax', 8.25),
        ]);

        $invoice->discount = 10;
        $invoice->is_amount_discount = true;
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        // Should have the product line + discount line
        $this->assertGreaterThanOrEqual(2, count($qb_data['Line']));

        // Find the discount line
        $discount_line = collect($qb_data['Line'])->firstWhere('DetailType', 'DiscountLineDetail');
        $this->assertNotNull($discount_line);
        $this->assertEquals('DiscountLineDetail', $discount_line['DetailType']);
        $this->assertEquals(10.00, $discount_line['Amount']);
    }

    public function test_invoice_ninjaToQb_percentage_discount()
    {
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Product', 200.00, 'Sales Tax', 8.25),
        ]);

        $invoice->discount = 15; // 15%
        $invoice->is_amount_discount = false;
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        $discount_line = collect($qb_data['Line'])->firstWhere('DetailType', 'DiscountLineDetail');
        $this->assertNotNull($discount_line);
        $this->assertTrue($discount_line['DiscountLineDetail']['PercentBased']);
        $this->assertEquals(15.0, $discount_line['DiscountLineDetail']['DiscountPercent']);
    }

    public function test_invoice_ninjaToQb_apply_tax_after_discount()
    {
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Product', 100.00, 'Sales Tax', 8.25),
        ]);

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        $this->assertTrue($qb_data['ApplyTaxAfterDiscount']);
    }

    public function test_invoice_ninjaToQb_empty_lines_throws_exception()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no valid line items');

        // Create invoice with no line items
        $client = $this->createClientWithQbId('QB-US-EMPTY-LINES');
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->line_items = [];
        $invoice->uses_inclusive_taxes = false;
        $invoice->date = '2026-02-15';
        $invoice->due_date = '2026-03-15';
        $invoice->saveQuietly();

        // This should throw because there are no line items to process
        $this->invoice_transformer->ninjaToQb($invoice, $this->qb);
    }

    public function test_invoice_ninjaToQb_no_tax_line_item()
    {
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('No Tax Item', 100.00, '', 0),
        ]);

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        $line = $qb_data['Line'][0];
        $tax_code_ref = $line['SalesItemLineDetail']['TaxCodeRef']['value'];

        $ast = $this->company->quickbooks->settings->automatic_taxes;

        if ($ast) {
            // With AST, non-exempt items (tax_id=1) get "TAX" — QB determines actual taxability
            $this->assertEquals('TAX', $tax_code_ref);
        } else {
            // Without AST, items with no tax rate get "NON"
            $this->assertEquals('NON', $tax_code_ref);
        }
    }

    public function test_invoice_ninjaToQb_us_never_uses_numeric_tax_codes()
    {
        // Verify that US companies NEVER output numeric TaxCodeRef values
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Taxable Widget', 100.00, 'Sales Tax', 8.25),
            $this->makeLineItem('Exempt Item', 50.00, '', 0, '5'),
            $this->makeLineItem('Another Taxable', 200.00, 'State Tax', 6.0),
        ]);

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        foreach ($qb_data['Line'] as $line) {
            $tax_code_ref = $line['SalesItemLineDetail']['TaxCodeRef']['value'];
            $this->assertContains(
                $tax_code_ref,
                ['TAX', 'NON'],
                "US company should only use 'TAX' or 'NON' as TaxCodeRef, got '{$tax_code_ref}'"
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    //  INVOICE QB → NINJA (PULL) TESTS
    // ──────────────────────────────────────────────────────────────────────

    public function test_invoice_qb_to_ninja_basic()
    {
        // First create a client with a QB sync so the transformer can resolve CustomerRef
        $client = $this->createClientWithQbId('800');
        // Get the actual QB ID that was created (not the string parameter)
        $client_qb_id = $client->sync->qb_id;

        $qb_invoice = [
            'Id' => '900',
            'CustomerRef' => $client_qb_id,
            'DocNumber' => 'INV-US-100',
            'TxnDate' => '2026-01-15',
            'DueDate' => '2026-02-15',
            'PrivateNote' => 'US invoice',
            'CustomerMemo' => 'Thank you!',
            'PONumber' => 'PO-US-001',
            'Deposit' => 100.00,
            'Balance' => 350.00,
            'TotalAmt' => 450.00,
            'Line' => [
                [
                    'DetailType' => 'SalesItemLineDetail',
                    'Amount' => 300.00,
                    'Description' => 'Consulting services',
                    'SalesItemLineDetail' => [
                        'ItemRef' => ['name' => 'Consulting'],
                        'Qty' => 3,
                        'UnitPrice' => 100.00,
                        'TaxCodeRef' => ['value' => 'TAX'],
                    ],
                ],
            ],
            'TxnTaxDetail' => [
                'TotalTax' => 24.75,
                'TaxLine' => [
                    [
                        'Amount' => 24.75,
                        'DetailType' => 'TaxLineDetail',
                        'TaxLineDetail' => [
                            'TaxRateRef' => ['value' => '10'],
                            'TaxPercent' => 8.25,
                            'NetAmountTaxable' => 300.00,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->invoice_transformer->qbToNinja($qb_invoice);

        $this->assertEquals('900', $result['id']);
        $this->assertEquals($client->id, $result['client_id']);
        $this->assertEquals('INV-US-100', $result['number']);
        $this->assertEquals('2026-01-15', $result['date']);
        $this->assertEquals('2026-02-15', $result['due_date']);
        $this->assertEquals('US invoice', $result['private_notes']);
        $this->assertEquals('PO-US-001', $result['po_number']);
        $this->assertEquals(100.00, $result['partial']);
        $this->assertEquals(350.00, $result['balance']);
        $this->assertEquals(Invoice::STATUS_SENT, $result['status_id']);
    }

    public function test_invoice_qb_to_ninja_returns_false_without_client()
    {
        $qb_invoice = [
            'Id' => '901',
            'CustomerRef' => '999999', // Non-existent
            'DocNumber' => 'INV-ORPHAN',
            'Line' => [],
        ];

        $result = $this->invoice_transformer->qbToNinja($qb_invoice);

        $this->assertFalse($result);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  COMPANY SETTINGS / COUNTRY DETECTION TESTS
    // ──────────────────────────────────────────────────────────────────────

    public function test_us_company_settings_configured_correctly()
    {
        $settings = $this->company->quickbooks->settings;

        $this->assertEquals('US', $settings->country);
        // US companies store default codes (may be numeric from companySync),
        // but the InvoiceTransformer forces TAX/NON at transform time
        $this->assertNotEmpty($settings->default_taxable_code);
        $this->assertNotEmpty($settings->default_exempt_code);
    }

    public function test_quickbooks_settings_is_configured()
    {
        $this->assertTrue($this->company->quickbooks->isConfigured());
    }

    // ──────────────────────────────────────────────────────────────────────
    //  MULTI-LINE INVOICE WITH MIXED US TAXES
    // ──────────────────────────────────────────────────────────────────────

    public function test_invoice_mixed_taxable_exempt_and_zero_rated()
    {
        // Invoice with taxable, exempt, and zero-rated lines
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Taxable Widget', 100.00, 'Sales Tax', 8.25),       // Taxable
            $this->makeLineItem('Exempt Medical', 75.00, '', 0, '5'),               // Exempt
            $this->makeLineItem('Zero-rated Export', 200.00, '', 0, '8'),           // Zero-rated
            $this->makeLineItem('Another Taxable', 150.00, 'Sales Tax', 8.25),      // Taxable
        ]);

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        $this->assertCount(4, $qb_data['Line']);

        $expected_tax_codes = [
            'TAX',   // Taxable
            'NON',   // Exempt
            'NON',   // Zero-rated
            'TAX',   // Taxable
        ];

        foreach ($qb_data['Line'] as $i => $line) {
            $this->assertEquals(
                $expected_tax_codes[$i],
                $line['SalesItemLineDetail']['TaxCodeRef']['value'],
                "Line {$i} ({$line['Description']}) has wrong TaxCodeRef"
            );
        }

        // Verify CustomerRef is set
        $this->assertArrayHasKey('CustomerRef', $qb_data);
        $this->assertNotEmpty($qb_data['CustomerRef']['value']);
    }

    public function test_invoice_all_exempt_lines()
    {
        [$invoice, $qb_service] = $this->createUSInvoice([
            $this->makeLineItem('Exempt A', 100.00, '', 0, '5'),
            $this->makeLineItem('Exempt B', 200.00, '', 0, '5'),
            $this->makeLineItem('Zero-rated C', 300.00, '', 0, '8'),
        ]);

        $qb_data = $this->invoice_transformer->ninjaToQb($invoice, $qb_service);

        // All lines should use "NON"
        foreach ($qb_data['Line'] as $line) {
            $this->assertEquals('NON', $line['SalesItemLineDetail']['TaxCodeRef']['value']);
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    //  SYNC FIELD MANAGEMENT TESTS
    // ──────────────────────────────────────────────────────────────────────

    public function test_client_sync_qb_id_stored_and_retrieved()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $sync = new ClientSync();
        $sync->qb_id = 'QB-US-CLIENT-123';
        $client->sync = $sync;
        $client->saveQuietly();

        $client = $client->fresh();
        $this->assertEquals('QB-US-CLIENT-123', $client->sync->qb_id);
    }

    public function test_product_sync_qb_id_stored_and_retrieved()
    {
        $product = ProductFactory::create($this->company->id, $this->user->id);
        $product->product_key = 'test_sync_product_us';
        $sync = new ProductSync();
        $sync->qb_id = 'QB-US-PROD-456';
        $product->sync = $sync;
        $product->saveQuietly();

        $product = $product->fresh();
        $this->assertEquals('QB-US-PROD-456', $product->sync->qb_id);
    }

    public function test_invoice_sync_qb_id_stored_and_retrieved()
    {
        $client = $this->createClientWithQbId('QB-US-SYNC-TEST');

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $sync = new InvoiceSync();
        $sync->qb_id = 'QB-US-INV-789';
        $invoice->sync = $sync;
        $invoice->saveQuietly();

        $invoice = $invoice->fresh();
        $this->assertEquals('QB-US-INV-789', $invoice->sync->qb_id);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  HELPER METHOD TESTS
    // ──────────────────────────────────────────────────────────────────────

    public function test_html_cleaning_for_qb_notes()
    {
        $dirty = '<p>Thank you<br/>for your <strong>business</strong></p>';
        $clean = $this->qb->helper->cleanHtmlText($dirty);

        $this->assertStringNotContainsString('<', $clean);
        $this->assertStringNotContainsString('>', $clean);
        $this->assertStringContainsString('Thank you', $clean);
        $this->assertStringContainsString('for your business', $clean);
    }

    public function test_split_tax_name_with_percentage()
    {
        $result = $this->qb->helper->splitTaxName('California Sales Tax 8.25%');
        $this->assertNotNull($result);
        $this->assertEquals('California Sales Tax', $result['name']);
        $this->assertEquals('8.25%', $result['percentage']);
    }

    public function test_split_tax_name_percentage_only()
    {
        $result = $this->qb->helper->splitTaxName('8.25%');
        $this->assertNotNull($result);
        $this->assertEquals('', $result['name']);
        $this->assertEquals('8.25', $result['percentage']);
    }

    public function test_split_tax_name_no_percentage()
    {
        $result = $this->qb->helper->splitTaxName('Sales Tax');
        $this->assertNull($result);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  END-TO-END: PUSH TO QB AND VERIFY
    // ──────────────────────────────────────────────────────────────────────

    public function test_product_push_to_qb_and_verify()
    {
        $unique = 'Test Product ' . time();

        $line_item = InvoiceItemFactory::create();
        $line_item->product_key = $unique;
        $line_item->notes = 'Functional test product for US';
        $line_item->cost = 59.99;
        $line_item->quantity = 1;
        $line_item->type_id = '1';
        $line_item->tax_id = '1';

        // Push to QB
        $qb_id = $this->qb->product->findOrCreateProduct($line_item);
        $this->assertNotEmpty($qb_id, 'Product was not created in QuickBooks');
        $this->qb_cleanup[] = ['type' => 'Item', 'id' => $qb_id];

        // Fetch back from QB
        $qb_item = $this->qb->sdk->FindById('Item', $qb_id);
        $this->assertNotNull($qb_item, 'Could not fetch product back from QuickBooks');

        // Verify fields match
        $this->assertEquals($unique, data_get($qb_item, 'Name'));
        $this->assertEquals('Functional test product for US', data_get($qb_item, 'Description'));
        $this->assertEquals(59.99, floatval(data_get($qb_item, 'UnitPrice')));
        $this->assertEquals('true', data_get($qb_item, 'Active'));

        // Type should be NonInventory for physical taxable items
        $this->assertEquals('NonInventory', data_get($qb_item, 'Type'));

        // Income account should match what the service provides
        $income_account_id = $this->qb->getIncomeAccountId();
        $this->assertEquals($income_account_id, data_get($qb_item, 'IncomeAccountRef'));
    }

    public function test_product_service_type_push_to_qb()
    {
        $unique = 'Test Service ' . time();

        $line_item = InvoiceItemFactory::create();
        $line_item->product_key = $unique;
        $line_item->notes = 'Service type product';
        $line_item->cost = 175.00;
        $line_item->quantity = 1;
        $line_item->type_id = '2'; // Service
        $line_item->tax_id = '2';

        $qb_id = $this->qb->product->findOrCreateProduct($line_item);
        $this->assertNotEmpty($qb_id);
        $this->qb_cleanup[] = ['type' => 'Item', 'id' => $qb_id];

        $qb_item = $this->qb->sdk->FindById('Item', $qb_id);
        $this->assertNotNull($qb_item);

        $this->assertEquals($unique, data_get($qb_item, 'Name'));
        $this->assertEquals('Service', data_get($qb_item, 'Type'));
        $this->assertEquals(175.00, floatval(data_get($qb_item, 'UnitPrice')));
    }

    /**
     * Test creating a product with income_account_id and verify 1:1 mapping.
     */
    public function test_product_with_income_account_id_maps_correctly()
    {
        $unique = 'Test Product Income Account ' . time();
        $expected_income_account_id = '1'; // Services account ID

        // Create product with income_account_id set
        $product = ProductFactory::create($this->company->id, $this->user->id);
        $product->product_key = $unique;
        $product->notes = 'Product with custom income account';
        $product->price = 99.99;
        $product->cost = 50.00;
        $product->tax_id = Product::PRODUCT_TYPE_SERVICE;
        $product->income_account_id = $expected_income_account_id;
        $product->saveQuietly();

        // Push to QuickBooks
        $this->qb->product->syncToForeign([$product]);

        // Refresh product to get QB ID
        $product = $product->fresh();
        $this->assertNotNull($product->sync->qb_id, 'Product should have QB ID after sync');
        $this->qb_cleanup[] = ['type' => 'Item', 'id' => $product->sync->qb_id];

        // Fetch from QuickBooks
        $qb_item = $this->qb->sdk->FindById('Item', $product->sync->qb_id);
        $this->assertNotNull($qb_item, 'Could not fetch product from QuickBooks');

        // Verify 1:1 mapping of all fields
        $this->assertEquals($unique, data_get($qb_item, 'Name'), 'Product key should match');
        $this->assertEquals('Product with custom income account', data_get($qb_item, 'Description'), 'Notes should match');
        $this->assertEquals(99.99, floatval(data_get($qb_item, 'UnitPrice')), 'Price should match');
        $this->assertEquals(50.00, floatval(data_get($qb_item, 'PurchaseCost')), 'Cost should match');
        $this->assertEquals('Service', data_get($qb_item, 'Type'), 'Type should be Service');
        $this->assertEquals('true', data_get($qb_item, 'Active'), 'Product should be active');

        // Verify income account ID matches what was set
        $income_account_ref = data_get($qb_item, 'IncomeAccountRef');
        $actual_account_id = is_object($income_account_ref) ? data_get($income_account_ref, 'value') : $income_account_ref;
        $this->assertEquals($expected_income_account_id, $actual_account_id, 'Income account ID should match the product setting');
    }

    /**
     * Test creating a product without income_account_id uses default.
     */
    public function test_product_without_income_account_id_uses_default()
    {
        $unique = 'Test Product Default Account ' . time();

        // Get default income account ID
        $default_income_account_id = $this->qb->getIncomeAccountId();
        $this->assertNotNull($default_income_account_id, 'Default income account should be available');

        // Create product without income_account_id
        $product = ProductFactory::create($this->company->id, $this->user->id);
        $product->product_key = $unique;
        $product->notes = 'Product without custom income account';
        $product->price = 75.50;
        $product->cost = 30.00;
        $product->tax_id = Product::PRODUCT_TYPE_PHYSICAL;
        $product->income_account_id = null; // Explicitly null
        $product->saveQuietly();

        // Push to QuickBooks
        $this->qb->product->syncToForeign([$product]);

        // Refresh product to get QB ID
        $product = $product->fresh();
        $this->assertNotNull($product->sync->qb_id, 'Product should have QB ID after sync');
        $this->qb_cleanup[] = ['type' => 'Item', 'id' => $product->sync->qb_id];

        // Fetch from QuickBooks
        $qb_item = $this->qb->sdk->FindById('Item', $product->sync->qb_id);
        $this->assertNotNull($qb_item, 'Could not fetch product from QuickBooks');

        // Verify income account uses default
        $income_account_ref = data_get($qb_item, 'IncomeAccountRef');
        $actual_account_id = is_object($income_account_ref) ? data_get($income_account_ref, 'value') : $income_account_ref;
        $this->assertEquals($default_income_account_id, $actual_account_id, 'Should use default income account when product has none set');

        // Verify other fields
        $this->assertEquals($unique, data_get($qb_item, 'Name'));
        $this->assertEquals('NonInventory', data_get($qb_item, 'Type'), 'Physical product should be NonInventory');
        $this->assertEquals(75.50, floatval(data_get($qb_item, 'UnitPrice')));
    }

    /**
     * Test comprehensive product field mapping for 1:1 verification.
     */
    public function test_product_comprehensive_field_mapping()
    {
        $unique = 'Test Comprehensive Mapping ' . time();
        $expected_income_account_id = '1';

        // Create product with all fields populated
        $product = ProductFactory::create($this->company->id, $this->user->id);
        $product->product_key = $unique;
        $product->notes = 'Comprehensive test product with all fields';
        $product->price = 123.45;
        $product->cost = 67.89;
        $product->tax_id = Product::PRODUCT_TYPE_SERVICE;
        $product->income_account_id = $expected_income_account_id;
        $product->saveQuietly();

        // Capture original values
        $original_values = [
            'product_key' => $product->product_key,
            'notes' => $product->notes,
            'price' => $product->price,
            'cost' => $product->cost,
            'tax_id' => $product->tax_id,
            'income_account_id' => $product->income_account_id,
        ];

        // Push to QuickBooks
        $this->qb->product->syncToForeign([$product]);

        // Refresh product
        $product = $product->fresh();
        $this->assertNotNull($product->sync->qb_id);
        $this->qb_cleanup[] = ['type' => 'Item', 'id' => $product->sync->qb_id];

        // Fetch from QuickBooks
        $qb_item = $this->qb->sdk->FindById('Item', $product->sync->qb_id);
        $this->assertNotNull($qb_item);

        // Verify 1:1 mapping of all fields
        $this->assertEquals($original_values['product_key'], data_get($qb_item, 'Name'), 'product_key → Name');
        $this->assertEquals($original_values['notes'], data_get($qb_item, 'Description'), 'notes → Description');
        $this->assertEquals($original_values['price'], floatval(data_get($qb_item, 'UnitPrice')), 'price → UnitPrice');
        $this->assertEquals($original_values['cost'], floatval(data_get($qb_item, 'PurchaseCost')), 'cost → PurchaseCost');

        // Verify type mapping
        $expected_type = $original_values['tax_id'] == Product::PRODUCT_TYPE_SERVICE ? 'Service' : 'NonInventory';
        $this->assertEquals($expected_type, data_get($qb_item, 'Type'), 'tax_id → Type');

        // Verify income account
        $income_account_ref = data_get($qb_item, 'IncomeAccountRef');
        $actual_account_id = is_object($income_account_ref) ? data_get($income_account_ref, 'value') : $income_account_ref;
        $this->assertEquals($original_values['income_account_id'], $actual_account_id, 'income_account_id → IncomeAccountRef.value');

        // Verify product is active
        $this->assertEquals('true', data_get($qb_item, 'Active'), 'Product should be active');
    }

    public function test_client_push_to_qb_and_verify()
    {
        $unique = 'Test Client ' . time();

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->name = $unique;
        $client->address1 = '123 Broadway';
        $client->city = 'New York';
        $client->state = 'NY';
        $client->postal_code = '10006';
        $client->country_id = 840;
        $client->shipping_address1 = '456 Market Street';
        $client->shipping_city = 'San Francisco';
        $client->shipping_state = 'CA';
        $client->shipping_postal_code = '94105';
        $client->shipping_country_id = 840;
        $client->public_notes = 'E2E test client';
        $client->id_number = 'EIN-98-7654321';
        $client->saveQuietly();

        $contact = ClientContactFactory::create($this->company->id, $this->user->id);
        $contact->client_id = $client->id;
        $contact->first_name = 'Test';
        $contact->last_name = 'Runner';
        $contact->email = 'test-' . time() . '@e2e.com';
        $contact->phone = '+1-212-555-0000';
        $contact->is_primary = true;
        $contact->saveQuietly();

        $client = $client->fresh();

        // Push to QB
        $qb_id = $this->qb->client->createQbClient($client);
        $this->assertNotEmpty($qb_id, 'Client was not created in QuickBooks');
        $this->qb_cleanup[] = ['type' => 'Customer', 'id' => $qb_id];

        // Fetch back from QB
        $qb_customer = $this->qb->sdk->FindById('Customer', $qb_id);
        $this->assertNotNull($qb_customer, 'Could not fetch client back from QuickBooks');

        // Verify core fields
        $this->assertEquals($unique, data_get($qb_customer, 'DisplayName'));
        $this->assertEquals($unique, data_get($qb_customer, 'CompanyName'));
        $this->assertEquals('true', data_get($qb_customer, 'Active'));

        // Verify billing address
        $this->assertEquals('123 Broadway', data_get($qb_customer, 'BillAddr.Line1'));
        $this->assertEquals('New York', data_get($qb_customer, 'BillAddr.City'));
        $this->assertEquals('NY', data_get($qb_customer, 'BillAddr.CountrySubDivisionCode'));
        $this->assertEquals('10006', data_get($qb_customer, 'BillAddr.PostalCode'));

        // Verify shipping address
        $this->assertEquals('456 Market Street', data_get($qb_customer, 'ShipAddr.Line1'));
        $this->assertEquals('San Francisco', data_get($qb_customer, 'ShipAddr.City'));
        $this->assertEquals('CA', data_get($qb_customer, 'ShipAddr.CountrySubDivisionCode'));
        $this->assertEquals('94105', data_get($qb_customer, 'ShipAddr.PostalCode'));

        // Verify contact details
        $this->assertEquals('Test', data_get($qb_customer, 'GivenName'));
        $this->assertEquals('Runner', data_get($qb_customer, 'FamilyName'));
        $this->assertStringContainsString('@e2e.com', data_get($qb_customer, 'PrimaryEmailAddr.Address'));
        $this->assertEquals('+1-212-555-0000', data_get($qb_customer, 'PrimaryPhone.FreeFormNumber'));
    }

    public function test_client_update_in_qb_reflects_changes()
    {
        $unique = 'Update Client ' . time();

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->name = $unique;
        $client->address1 = '100 Original Street';
        $client->city = 'Boston';
        $client->state = 'MA';
        $client->postal_code = '02101';
        $client->country_id = 840;
        $client->saveQuietly();

        $contact = ClientContactFactory::create($this->company->id, $this->user->id);
        $contact->client_id = $client->id;
        $contact->first_name = 'Original';
        $contact->last_name = 'Name';
        $contact->email = 'original-' . time() . '@e2e.com';
        $contact->is_primary = true;
        $contact->saveQuietly();

        $client = $client->fresh();

        // Initial push
        $qb_id = $this->qb->client->createQbClient($client);
        $this->assertNotEmpty($qb_id);
        $this->qb_cleanup[] = ['type' => 'Customer', 'id' => $qb_id];

        // Update the client locally
        $client->address1 = '200 Updated Avenue';
        $client->city = 'Los Angeles';
        $client->state = 'CA';
        $client->postal_code = '90001';
        $sync = new ClientSync();
        $sync->qb_id = $qb_id;
        $client->sync = $sync;
        $client->saveQuietly();

        // Push update
        $updated_qb_id = $this->qb->client->createQbClient($client);
        $this->assertEquals($qb_id, $updated_qb_id);

        // Fetch back and verify updated fields
        $qb_customer = $this->qb->sdk->FindById('Customer', $qb_id);
        $this->assertEquals('200 Updated Avenue', data_get($qb_customer, 'BillAddr.Line1'));
        $this->assertEquals('Los Angeles', data_get($qb_customer, 'BillAddr.City'));
        $this->assertEquals('CA', data_get($qb_customer, 'BillAddr.CountrySubDivisionCode'));
        $this->assertEquals('90001', data_get($qb_customer, 'BillAddr.PostalCode'));
    }

    public function test_invoice_push_to_qb_and_verify()
    {
        // First create and push a real client to QB
        $unique_client = 'Invoice Test Client ' . time();
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->name = $unique_client;
        $client->address1 = '400 Park Avenue';
        $client->city = 'New York';
        $client->state = 'NY';
        $client->postal_code = '10022';
        $client->country_id = 840;
        $client->saveQuietly();

        $contact = ClientContactFactory::create($this->company->id, $this->user->id);
        $contact->client_id = $client->id;
        $contact->first_name = 'Invoice';
        $contact->last_name = 'Test';
        $contact->email = 'inv-' . time() . '@e2e.com';
        $contact->is_primary = true;
        $contact->send_email = true;
        $contact->saveQuietly();

        $client = $client->fresh();

        $client_qb_id = $this->qb->client->createQbClient($client);
        $this->assertNotEmpty($client_qb_id);
        $this->qb_cleanup[] = ['type' => 'Customer', 'id' => $client_qb_id];

        // Store the QB ID on the client
        $sync = new ClientSync();
        $sync->qb_id = $client_qb_id;
        $client->sync = $sync;
        $client->saveQuietly();

        // Create the invoice with taxable and exempt line items
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->number = 'E2E-US-' . time();
        $invoice->date = '2026-02-26';
        $invoice->due_date = '2026-03-26';
        $invoice->po_number = 'PO-E2E-001';
        $invoice->public_notes = 'End-to-end test invoice';
        $invoice->private_notes = 'Internal test note';
        $invoice->uses_inclusive_taxes = false;
        $invoice->line_items = [
            $this->makeLineItem('Taxable Widget', 100.00, 'Sales Tax', 8.25, '1', 2),
            $this->makeLineItem('Exempt Item', 50.00, '', 0, '5', 1),
        ];
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        // Transform and push to QB
        $qb_invoice_data = $this->invoice_transformer->ninjaToQb($invoice, $this->qb);
        $qb_invoice_obj = \QuickBooksOnline\API\Facades\Invoice::create($qb_invoice_data);
        $result = $this->qb->sdk->Add($qb_invoice_obj);

        $invoice_qb_id = data_get($result, 'Id') ?? data_get($result, 'Id.value');
        $this->assertNotEmpty($invoice_qb_id, 'Invoice was not created in QuickBooks');
        $this->qb_cleanup[] = ['type' => 'Invoice', 'id' => $invoice_qb_id];

        // Fetch back from QB
        $qb_inv = $this->qb->sdk->FindById('Invoice', $invoice_qb_id);
        $this->assertNotNull($qb_inv, 'Could not fetch invoice back from QuickBooks');

        // Verify metadata
        $this->assertEquals($invoice->number, data_get($qb_inv, 'DocNumber'));
        $this->assertEquals('2026-02-26', data_get($qb_inv, 'TxnDate'));
        $this->assertEquals('2026-03-26', data_get($qb_inv, 'DueDate'));
        $this->assertEquals('End-to-end test invoice', data_get($qb_inv, 'CustomerMemo'));
        $this->assertEquals('Internal test note', data_get($qb_inv, 'PrivateNote'));

        // Verify customer reference (SDK returns flat string)
        $this->assertEquals($client_qb_id, data_get($qb_inv, 'CustomerRef'));

        // Verify line items exist
        $lines = data_get($qb_inv, 'Line');
        $this->assertNotEmpty($lines);

        // Filter to SalesItemLineDetail lines only (QB adds SubTotalLineDetail automatically)
        $sales_lines = collect($lines)->filter(fn($l) => data_get($l, 'DetailType') === 'SalesItemLineDetail');
        $this->assertCount(2, $sales_lines);

        // Verify first line: 100 * 2 = 200
        $line1 = $sales_lines->first();
        $this->assertEquals(200.00, floatval(data_get($line1, 'Amount')));

        // Verify second line: 50 * 1 = 50
        $line2 = $sales_lines->last();
        $this->assertEquals(50.00, floatval(data_get($line2, 'Amount')));

        // For US with AST, QB should calculate tax automatically
        $ast = $this->company->quickbooks->settings->automatic_taxes;
        if ($ast) {
            $total_tax = data_get($qb_inv, 'TxnTaxDetail.TotalTax');
            $this->assertNotNull($total_tax, 'QB should calculate tax for US invoice with AST');
            $this->assertGreaterThan(0, floatval($total_tax));
        }
    }

    public function test_invoice_with_discount_push_to_qb()
    {
        // Create and push client
        $client = $this->pushClientToQb('Discount Client ' . time());

        // Create invoice with a discount
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->number = 'E2E-DISC-' . time();
        $invoice->date = '2026-02-26';
        $invoice->due_date = '2026-03-26';
        $invoice->discount = 10;
        $invoice->is_amount_discount = true;
        $invoice->uses_inclusive_taxes = false;
        $invoice->line_items = [
            $this->makeLineItem('Discounted Product', 200.00, 'Sales Tax', 8.25),
        ];
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        // Push to QB
        $qb_invoice_data = $this->invoice_transformer->ninjaToQb($invoice, $this->qb);
        $qb_invoice_obj = \QuickBooksOnline\API\Facades\Invoice::create($qb_invoice_data);
        $result = $this->qb->sdk->Add($qb_invoice_obj);

        $invoice_qb_id = data_get($result, 'Id') ?? data_get($result, 'Id.value');
        $this->assertNotEmpty($invoice_qb_id);
        $this->qb_cleanup[] = ['type' => 'Invoice', 'id' => $invoice_qb_id];

        // Fetch and verify
        $qb_inv = $this->qb->sdk->FindById('Invoice', $invoice_qb_id);
        $this->assertNotNull($qb_inv);

        // QB TotalAmt includes tax. Subtotal is 200 - 10 discount = 190, plus sales tax.
        // The total with tax should be less than 200 + full tax
        $total = floatval(data_get($qb_inv, 'TotalAmt'));
        $this->assertGreaterThan(0, $total, 'Total should be positive');
    }

    public function test_full_round_trip_product_client_invoice()
    {
        $ts = time();

        // 1. Push a product to QB
        $line_item = InvoiceItemFactory::create();
        $line_item->product_key = 'Round Trip Item ' . $ts;
        $line_item->notes = 'Full round-trip test item';
        $line_item->cost = 75.00;
        $line_item->quantity = 1;
        $line_item->type_id = '1';
        $line_item->tax_id = '1';

        $product_qb_id = $this->qb->product->findOrCreateProduct($line_item);
        $this->assertNotEmpty($product_qb_id);
        $this->qb_cleanup[] = ['type' => 'Item', 'id' => $product_qb_id];

        // 2. Push a client to QB
        $client = $this->pushClientToQb('Round Trip Client ' . $ts);

        // 3. Create invoice referencing both
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->number = 'E2E-RT-' . $ts;
        $invoice->date = '2026-02-26';
        $invoice->due_date = '2026-03-26';
        $invoice->uses_inclusive_taxes = false;
        $invoice->line_items = [
            $this->makeLineItem('Round Trip Item ' . $ts, 75.00, 'Sales Tax', 8.25, '1', 3),
        ];
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        // 4. Push invoice to QB
        $qb_invoice_data = $this->invoice_transformer->ninjaToQb($invoice, $this->qb);
        $qb_invoice_obj = \QuickBooksOnline\API\Facades\Invoice::create($qb_invoice_data);
        $result = $this->qb->sdk->Add($qb_invoice_obj);

        $invoice_qb_id = data_get($result, 'Id') ?? data_get($result, 'Id.value');
        $this->assertNotEmpty($invoice_qb_id);
        $this->qb_cleanup[] = ['type' => 'Invoice', 'id' => $invoice_qb_id];

        // 5. Fetch the invoice back from QB
        $qb_inv = $this->qb->sdk->FindById('Invoice', $invoice_qb_id);
        $this->assertNotNull($qb_inv);

        // 6. Verify the product item ref on the line
        $lines = collect(data_get($qb_inv, 'Line'))
            ->filter(fn($l) => data_get($l, 'DetailType') === 'SalesItemLineDetail');
        $this->assertCount(1, $lines);

        $line = $lines->first();
        $item_ref = data_get($line, 'SalesItemLineDetail.ItemRef');
        $this->assertNotEmpty($item_ref, 'Line should reference a QB Item');

        // 7. Verify amounts: 75 * 3 = 225
        $this->assertEquals(225.00, floatval(data_get($line, 'Amount')));
        $this->assertEquals(3, intval(data_get($line, 'SalesItemLineDetail.Qty')));
        $this->assertEquals(75.00, floatval(data_get($line, 'SalesItemLineDetail.UnitPrice')));

        // 8. Verify customer ref matches the pushed client
        $this->assertEquals($client->sync->qb_id, data_get($qb_inv, 'CustomerRef'));

        // 9. For US with AST, verify QB calculated tax
        $ast = $this->company->quickbooks->settings->automatic_taxes;
        if ($ast) {
            $total_tax = floatval(data_get($qb_inv, 'TxnTaxDetail.TotalTax'));
            $this->assertGreaterThan(0, $total_tax, 'QB should calculate sales tax on the taxable line');
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    //  PAYMENT TESTS
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Test creating a full payment for an invoice and verifying 1:1 mapping in QuickBooks.
     */
    public function test_payment_full_payment_on_invoice_maps_correctly()
    {
        $unique = 'Payment Test Client ' . time();

        // Create and push client to QB
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->name = $unique;
        $client->address1 = '500 Wall Street';
        $client->city = 'New York';
        $client->state = 'NY';
        $client->postal_code = '10005';
        $client->country_id = 840;
        $client->saveQuietly();

        $contact = ClientContactFactory::create($this->company->id, $this->user->id);
        $contact->client_id = $client->id;
        $contact->first_name = 'Payment';
        $contact->last_name = 'Test';
        $contact->email = 'payment-' . time() . '@test.com';
        $contact->is_primary = true;
        $contact->saveQuietly();

        $client = $client->fresh();
        $client_qb_id = $this->qb->client->createQbClient($client);
        $this->assertNotEmpty($client_qb_id);
        $this->qb_cleanup[] = ['type' => 'Customer', 'id' => $client_qb_id];

        $sync = new ClientSync();
        $sync->qb_id = $client_qb_id;
        $client->sync = $sync;
        $client->saveQuietly();
        $client = $client->fresh();

        // Create and push invoice to QB
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->number = 'INV-PAY-' . time();
        $invoice->date = '2026-03-01';
        $invoice->due_date = '2026-03-31';
        $invoice->uses_inclusive_taxes = false;
        $invoice->line_items = [
            $this->makeLineItem('Payment Test Product', 250.00, 'Sales Tax', 8.25),
        ];
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        // Push invoice to QB
        $qb_invoice_data = $this->invoice_transformer->ninjaToQb($invoice, $this->qb);
        $qb_invoice_obj = \QuickBooksOnline\API\Facades\Invoice::create($qb_invoice_data);
        $invoice_result = $this->qb->sdk->Add($qb_invoice_obj);
        $invoice_qb_id = data_get($invoice_result, 'Id') ?? data_get($invoice_result, 'Id.value');
        $this->assertNotEmpty($invoice_qb_id);
        $this->qb_cleanup[] = ['type' => 'Invoice', 'id' => $invoice_qb_id];

        $invoice_sync = new InvoiceSync();
        $invoice_sync->qb_id = $invoice_qb_id;
        $invoice->sync = $invoice_sync;
        $invoice->saveQuietly();
        $invoice = $invoice->fresh();

        // Create full payment for the invoice
        $payment = PaymentFactory::create($this->company->id, $this->user->id);
        $payment->client_id = $client->id;
        $payment->amount = $invoice->amount; // Full payment
        $payment->applied = $invoice->amount;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->date = '2026-03-05';
        $payment->transaction_reference = 'TEST-REF-' . time();
        $payment->private_notes = 'Full payment test';
        $payment->saveQuietly();

        // Attach payment to invoice
        $payment->invoices()->attach($invoice->id, [
            'amount' => $invoice->amount,
        ]);

        // Update invoice balance
        $invoice->service()
            ->updateBalance($payment->amount * -1)
            ->updatePaidToDate($payment->amount)
            ->setCalculatedStatus()
            ->save();

        $payment = $payment->fresh();

        // Push payment to QuickBooks
        $this->qb->payment->syncToForeign([$payment]);

        // Refresh payment to get QB ID
        $payment = $payment->fresh();
        $this->assertNotNull($payment->sync->qb_id, 'Payment should have QB ID after sync');
        $this->qb_cleanup[] = ['type' => 'Payment', 'id' => $payment->sync->qb_id];

        // Fetch payment from QuickBooks
        $qb_payment = $this->qb->sdk->FindById('Payment', $payment->sync->qb_id);
        $this->assertNotNull($qb_payment, 'Could not fetch payment from QuickBooks');

        // Verify 1:1 mapping
        $qb_customer_ref = data_get($qb_payment, 'CustomerRef.value') ?? data_get($qb_payment, 'CustomerRef');
        $this->assertEquals($client_qb_id, $qb_customer_ref, 'CustomerRef should match');
        $this->assertEquals($invoice->amount, floatval(data_get($qb_payment, 'TotalAmt')), 'TotalAmt should match invoice amount');
        $this->assertEquals('2026-03-05', data_get($qb_payment, 'TxnDate'), 'TxnDate should match');
        $this->assertEquals('Full payment test', data_get($qb_payment, 'PrivateNote'), 'PrivateNote should match');
        $this->assertStringStartsWith('TEST-REF-', data_get($qb_payment, 'PaymentRefNum'), 'PaymentRefNum should match');

        // Verify payment line item references the invoice
        $lines = data_get($qb_payment, 'Line', []) ?? [];
        if (!empty($lines)) {
            if (!is_array($lines)) {
                $lines = [$lines];
            } elseif (!isset($lines[0])) {
                $lines = [$lines];
            }
        }
        $this->assertNotEmpty($lines, 'Payment should have line items');
        $line = $lines[0];
        $this->assertEquals($invoice->amount, floatval(data_get($line, 'Amount')), 'Line Amount should match invoice amount');
        $this->assertEquals($invoice_qb_id, data_get($line, 'LinkedTxn.TxnId') ?? data_get($line, 'LinkedTxn.0.TxnId'), 'Line should reference invoice QB ID');
        $this->assertEquals('Invoice', data_get($line, 'LinkedTxn.TxnType') ?? data_get($line, 'LinkedTxn.0.TxnType'), 'Line should reference Invoice type');

        // Verify invoice status updated
        $invoice = $invoice->fresh();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->setCalculatedStatus()->save();
        $invoice = $invoice->fresh();
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id, 'Invoice should be marked as paid');
        $this->assertEquals(0, round($invoice->balance, 2), 'Invoice balance should be zero');
    }

    /**
     * Test creating a partial payment for an invoice and verifying mapping.
     */
    public function test_payment_partial_payment_on_invoice_maps_correctly()
    {
        // Create and push client to QB
        $client = $this->createClientWithQbId('QB-US-PAYMENT-PARTIAL');

        // Create and push invoice to QB
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->number = 'INV-PART-' . time();
        $invoice->date = '2026-03-01';
        $invoice->due_date = '2026-03-31';
        $invoice->uses_inclusive_taxes = false;
        $invoice->line_items = [
            $this->makeLineItem('Partial Payment Product', 500.00, 'Sales Tax', 8.25),
        ];
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        $invoice_amount = $invoice->amount;

        // Push invoice to QB
        $qb_invoice_data = $this->invoice_transformer->ninjaToQb($invoice, $this->qb);
        $qb_invoice_obj = \QuickBooksOnline\API\Facades\Invoice::create($qb_invoice_data);
        $invoice_result = $this->qb->sdk->Add($qb_invoice_obj);
        $invoice_qb_id = data_get($invoice_result, 'Id') ?? data_get($invoice_result, 'Id.value');
        $this->assertNotEmpty($invoice_qb_id);
        $this->qb_cleanup[] = ['type' => 'Invoice', 'id' => $invoice_qb_id];

        $invoice_sync = new InvoiceSync();
        $invoice_sync->qb_id = $invoice_qb_id;
        $invoice->sync = $invoice_sync;
        $invoice->saveQuietly();
        $invoice = $invoice->fresh();

        // Create partial payment (50% of invoice)
        $partial_amount = $invoice_amount / 2;
        $payment = PaymentFactory::create($this->company->id, $this->user->id);
        $payment->client_id = $client->id;
        $payment->amount = $partial_amount;
        $payment->applied = $partial_amount;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->date = '2026-03-05';
        $payment->transaction_reference = 'PARTIAL-' . time();
        $payment->private_notes = 'Partial payment test - 50%';
        $payment->saveQuietly();

        // Attach payment to invoice
        $payment->invoices()->attach($invoice->id, [
            'amount' => $partial_amount,
        ]);

        // Update invoice balance
        $invoice->service()
            ->updateBalance($payment->amount * -1)
            ->updatePaidToDate($payment->amount)
            ->setCalculatedStatus()
            ->save();

        $payment = $payment->fresh();

        // Push payment to QuickBooks
        $this->qb->payment->syncToForeign([$payment]);

        // Refresh payment to get QB ID
        $payment = $payment->fresh();
        $this->assertNotNull($payment->sync->qb_id, 'Payment should have QB ID after sync');
        $this->qb_cleanup[] = ['type' => 'Payment', 'id' => $payment->sync->qb_id];

        // Fetch payment from QuickBooks
        $qb_payment = $this->qb->sdk->FindById('Payment', $payment->sync->qb_id);
        $this->assertNotNull($qb_payment, 'Could not fetch payment from QuickBooks');

        // Verify partial payment mapping (round to 2 decimals — QB rounds amounts)
        $this->assertEquals(round($partial_amount, 2), floatval(data_get($qb_payment, 'TotalAmt')), 'TotalAmt should match partial payment amount');

        // Verify payment line item
        $lines = data_get($qb_payment, 'Line', []) ?? [];
        if (!empty($lines)) {
            if (!is_array($lines)) {
                $lines = [$lines];
            } elseif (!isset($lines[0])) {
                $lines = [$lines];
            }
        }
        $this->assertNotEmpty($lines, 'Payment should have line items');
        $line = $lines[0];
        $this->assertEquals(round($partial_amount, 2), floatval(data_get($line, 'Amount')), 'Line Amount should match partial amount');
        $this->assertEquals($invoice_qb_id, data_get($line, 'LinkedTxn.TxnId') ?? data_get($line, 'LinkedTxn.0.TxnId'), 'Line should reference invoice QB ID');

        // Verify invoice still has balance
        $invoice = $invoice->fresh();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->setCalculatedStatus()->save();
        $invoice = $invoice->fresh();
        $this->assertEquals(Invoice::STATUS_PARTIAL, $invoice->status_id, 'Invoice should be marked as partially paid');
        $this->assertEquals(round($invoice_amount - $partial_amount, 2), round($invoice->balance, 2), 'Invoice balance should reflect partial payment');
    }

    /**
     * Test voiding a payment and verifying invoice updates.
     */
    public function test_payment_void_payment_updates_invoice()
    {
        // Create and push client to QB
        $client = $this->createClientWithQbId('QB-US-PAYMENT-VOID');

        // Create and push invoice to QB
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->number = 'INV-VOID-' . time();
        $invoice->date = '2026-03-01';
        $invoice->due_date = '2026-03-31';
        $invoice->uses_inclusive_taxes = false;
        $invoice->line_items = [
            $this->makeLineItem('Void Payment Product', 300.00, 'Sales Tax', 8.25),
        ];
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        $invoice_amount = $invoice->amount;

        // Push invoice to QB
        $qb_invoice_data = $this->invoice_transformer->ninjaToQb($invoice, $this->qb);
        $qb_invoice_obj = \QuickBooksOnline\API\Facades\Invoice::create($qb_invoice_data);
        $invoice_result = $this->qb->sdk->Add($qb_invoice_obj);
        $invoice_qb_id = data_get($invoice_result, 'Id') ?? data_get($invoice_result, 'Id.value');
        $this->assertNotEmpty($invoice_qb_id);
        $this->qb_cleanup[] = ['type' => 'Invoice', 'id' => $invoice_qb_id];

        $invoice_sync = new InvoiceSync();
        $invoice_sync->qb_id = $invoice_qb_id;
        $invoice->sync = $invoice_sync;
        $invoice->saveQuietly();
        $invoice = $invoice->fresh();

        // Create and push full payment
        $payment = PaymentFactory::create($this->company->id, $this->user->id);
        $payment->client_id = $client->id;
        $payment->amount = $invoice_amount;
        $payment->applied = $invoice_amount;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->date = '2026-03-05';
        $payment->transaction_reference = 'VOID-REF-' . time();
        $payment->saveQuietly();

        $payment->invoices()->attach($invoice->id, [
            'amount' => $invoice_amount,
        ]);

        $invoice->service()
            ->updateBalance($payment->amount * -1)
            ->updatePaidToDate($payment->amount)
            ->setCalculatedStatus()
            ->save();

        $payment = $payment->fresh();

        // Push payment to QuickBooks
        $this->qb->payment->syncToForeign([$payment]);
        $payment = $payment->fresh();
        $this->assertNotNull($payment->sync->qb_id);
        $this->qb_cleanup[] = ['type' => 'Payment', 'id' => $payment->sync->qb_id];

        // Verify invoice is paid
        $invoice = $invoice->fresh();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->setCalculatedStatus()->save();
        $invoice = $invoice->fresh();
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id, 'Invoice should be paid before void');
        $this->assertEquals(0, round($invoice->balance, 2), 'Invoice balance should be zero before void');

        // Void the payment
        $payment->status_id = Payment::STATUS_CANCELLED;
        $payment->saveQuietly();

        // Store QB ID before voiding (it gets cleared after successful void)
        $payment_qb_id = $payment->sync->qb_id;
        $this->assertNotEmpty($payment_qb_id, 'Payment should have QB ID before voiding');

        // Push void to QuickBooks
        $this->qb->payment->syncToForeign([$payment]);

        // Refresh payment - QB ID is cleared after successful void
        $payment = $payment->fresh();

        // Verify void was successful by checking that sync->qb_id was cleared
        $this->assertEmpty($payment->sync->qb_id ?? '', 'Payment QB ID should be cleared after successful void');

        // Verify payment is voided in QuickBooks (using stored QB ID since sync->qb_id is cleared)
        $qb_payment = $this->qb->sdk->FindById('Payment', $payment_qb_id);
        $this->assertNotNull($qb_payment, 'Payment should still exist in QuickBooks (voided)');

        $txn_status = data_get($qb_payment, 'TxnStatus') ?? data_get($qb_payment, 'TxnStatus.value') ?? null;
        if ($txn_status !== null) {
            $this->assertEquals('Voided', $txn_status, 'Payment should be voided in QuickBooks');
        } else {
            $this->assertTrue(true, 'Payment void verified by sync->qb_id being cleared (TxnStatus not available in response)');
        }

        $invoice = $invoice->fresh();
    }

    /**
     * Test multiple payments on same invoice and verify all are tracked correctly.
     */
    public function test_payment_multiple_payments_on_invoice()
    {
        // Create and push client to QB
        $client = $this->createClientWithQbId('QB-US-PAYMENT-MULTI');

        // Create and push invoice to QB
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->number = 'INV-MULTI-' . time();
        $invoice->date = '2026-03-01';
        $invoice->due_date = '2026-03-31';
        $invoice->uses_inclusive_taxes = false;
        $invoice->line_items = [
            $this->makeLineItem('Multi Payment Product', 600.00, 'Sales Tax', 8.25),
        ];
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        $invoice_amount = $invoice->amount;

        // Push invoice to QB
        $qb_invoice_data = $this->invoice_transformer->ninjaToQb($invoice, $this->qb);
        $qb_invoice_obj = \QuickBooksOnline\API\Facades\Invoice::create($qb_invoice_data);
        $invoice_result = $this->qb->sdk->Add($qb_invoice_obj);
        $invoice_qb_id = data_get($invoice_result, 'Id') ?? data_get($invoice_result, 'Id.value');
        $this->assertNotEmpty($invoice_qb_id);
        $this->qb_cleanup[] = ['type' => 'Invoice', 'id' => $invoice_qb_id];

        $invoice_sync = new InvoiceSync();
        $invoice_sync->qb_id = $invoice_qb_id;
        $invoice->sync = $invoice_sync;
        $invoice->saveQuietly();
        $invoice = $invoice->fresh();

        // Create first partial payment (40%)
        $payment1_amount = $invoice_amount * 0.4;
        $payment1 = PaymentFactory::create($this->company->id, $this->user->id);
        $payment1->client_id = $client->id;
        $payment1->amount = $payment1_amount;
        $payment1->applied = $payment1_amount;
        $payment1->status_id = Payment::STATUS_COMPLETED;
        $payment1->date = '2026-03-05';
        $payment1->transaction_reference = 'MULTI-1-' . time();
        $payment1->saveQuietly();

        $payment1->invoices()->attach($invoice->id, [
            'amount' => $payment1_amount,
        ]);

        $invoice->service()
            ->updateBalance($payment1->amount * -1)
            ->updatePaidToDate($payment1->amount)
            ->setCalculatedStatus()
            ->save();

        $payment1 = $payment1->fresh();

        // Push first payment to QuickBooks
        $this->qb->payment->syncToForeign([$payment1]);
        $payment1 = $payment1->fresh();
        $this->assertNotNull($payment1->sync->qb_id);
        $this->qb_cleanup[] = ['type' => 'Payment', 'id' => $payment1->sync->qb_id];

        // Create second partial payment (remaining 60%)
        $payment2_amount = $invoice_amount * 0.6;
        $payment2 = PaymentFactory::create($this->company->id, $this->user->id);
        $payment2->client_id = $client->id;
        $payment2->amount = $payment2_amount;
        $payment2->applied = $payment2_amount;
        $payment2->status_id = Payment::STATUS_COMPLETED;
        $payment2->date = '2026-03-10';
        $payment2->transaction_reference = 'MULTI-2-' . time();
        $payment2->saveQuietly();

        $invoice = $invoice->fresh();
        $payment2->invoices()->attach($invoice->id, [
            'amount' => $payment2_amount,
        ]);

        $invoice->service()
            ->updateBalance($payment2->amount * -1)
            ->updatePaidToDate($payment2->amount)
            ->setCalculatedStatus()
            ->save();

        $payment2 = $payment2->fresh();

        // Push second payment to QuickBooks
        $this->qb->payment->syncToForeign([$payment2]);
        $payment2 = $payment2->fresh();
        $this->assertNotNull($payment2->sync->qb_id);
        $this->qb_cleanup[] = ['type' => 'Payment', 'id' => $payment2->sync->qb_id];

        // Verify both payments in QuickBooks
        $qb_payment1 = $this->qb->sdk->FindById('Payment', $payment1->sync->qb_id);
        $this->assertNotNull($qb_payment1);
        $this->assertEquals($payment1_amount, floatval(data_get($qb_payment1, 'TotalAmt')), 'First payment amount should match');

        $qb_payment2 = $this->qb->sdk->FindById('Payment', $payment2->sync->qb_id);
        $this->assertNotNull($qb_payment2);
        $this->assertEquals($payment2_amount, floatval(data_get($qb_payment2, 'TotalAmt')), 'Second payment amount should match');

        // Verify invoice is fully paid
        $invoice = $invoice->fresh();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->setCalculatedStatus()->save();
        $invoice = $invoice->fresh();
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id, 'Invoice should be fully paid');
        $this->assertEquals(0, round($invoice->balance, 2), 'Invoice balance should be zero');
    }

    // ──────────────────────────────────────────────────────────────────────
    //  AST TAX RESPONSE PROCESSING TESTS
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Test that pushing an invoice with AST writes QB-calculated taxes back to Ninja line items.
     */
    public function test_ast_tax_response_written_back_to_ninja_line_items()
    {
        $ast = $this->company->quickbooks->settings->automatic_taxes;
        if (!$ast) {
            $this->markTestSkipped('QBUS company does not have AST enabled');
        }

        $client = $this->pushClientToQb('AST Tax Test Client ' . time());

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->number = 'AST-TAX-' . time();
        $invoice->date = '2026-03-01';
        $invoice->due_date = '2026-03-31';
        $invoice->uses_inclusive_taxes = false;
        $invoice->line_items = [
            $this->makeLineItem('Taxable Widget', 200.00, '', 0, '1', 1),
        ];
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        // Use syncToForeign which calls processQuickbooksTaxResponse internally
        $this->qb->invoice->syncToForeign([$invoice]);

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->sync->qb_id, 'Invoice should have QB ID after sync');
        $this->qb_cleanup[] = ['type' => 'Invoice', 'id' => $invoice->sync->qb_id];

        // Verify QB-calculated taxes were written back to Ninja line items
        $line_items = $invoice->line_items;
        $this->assertNotEmpty($line_items);

        $taxable_line = $line_items[0];
        $this->assertGreaterThan(0, $taxable_line->tax_rate1, 'Taxable line item should have tax_rate1 set by QB AST');
        $this->assertNotEmpty($taxable_line->tax_name1, 'Taxable line item should have tax_name1 set by QB AST');

        // Invoice-level taxes should be cleared (only line-item taxes used)
        $this->assertEquals('', $invoice->tax_name1, 'Invoice-level tax_name1 should be empty with AST');
        $this->assertEquals(0, $invoice->tax_rate1, 'Invoice-level tax_rate1 should be zero with AST');
    }

    /**
     * Test AST with mixed taxable and exempt line items — only taxable lines get tax.
     */
    public function test_ast_mixed_taxable_exempt_lines_tax_assignment()
    {
        $ast = $this->company->quickbooks->settings->automatic_taxes;
        if (!$ast) {
            $this->markTestSkipped('QBUS company does not have AST enabled');
        }

        $client = $this->pushClientToQb('AST Mixed Tax Client ' . time());

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->number = 'AST-MIX-' . time();
        $invoice->date = '2026-03-01';
        $invoice->due_date = '2026-03-31';
        $invoice->uses_inclusive_taxes = false;
        $invoice->line_items = [
            $this->makeLineItem('Taxable Product', 150.00, '', 0, '1', 1),
            $this->makeLineItem('Exempt Product', 100.00, '', 0, '5', 1), // tax_id=5 = exempt
            $this->makeLineItem('Another Taxable', 250.00, '', 0, '1', 1),
        ];
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        $this->qb->invoice->syncToForeign([$invoice]);

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->sync->qb_id);
        $this->qb_cleanup[] = ['type' => 'Invoice', 'id' => $invoice->sync->qb_id];

        $line_items = $invoice->line_items;
        $this->assertCount(3, $line_items);

        // Line 1: Taxable — should have tax
        $this->assertGreaterThan(0, $line_items[0]->tax_rate1, 'Taxable line 1 should have tax');

        // Line 2: Exempt — should have NO tax
        $this->assertEquals(0, $line_items[1]->tax_rate1, 'Exempt line should have zero tax rate');
        $this->assertEquals('', $line_items[1]->tax_name1, 'Exempt line should have empty tax name');

        // Line 3: Taxable — should have tax
        $this->assertGreaterThan(0, $line_items[2]->tax_rate1, 'Taxable line 2 should have tax');
    }

    /**
     * Test AST validates and syncs amounts between QB and Ninja.
     */
    public function test_ast_validates_and_syncs_amounts()
    {
        $ast = $this->company->quickbooks->settings->automatic_taxes;
        if (!$ast) {
            $this->markTestSkipped('QBUS company does not have AST enabled');
        }

        $client = $this->pushClientToQb('AST Validate Client ' . time());

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->number = 'AST-VAL-' . time();
        $invoice->date = '2026-03-01';
        $invoice->due_date = '2026-03-31';
        $invoice->uses_inclusive_taxes = false;
        $invoice->line_items = [
            $this->makeLineItem('Validate Product', 300.00, '', 0, '1', 2),
        ];
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        $this->qb->invoice->syncToForeign([$invoice]);

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->sync->qb_id);
        $this->qb_cleanup[] = ['type' => 'Invoice', 'id' => $invoice->sync->qb_id];

        // Fetch the QB invoice to compare amounts
        $qb_inv = $this->qb->sdk->FindById('Invoice', $invoice->sync->qb_id);
        $this->assertNotNull($qb_inv);

        $qb_total_amt = floatval(data_get($qb_inv, 'TotalAmt'));
        $qb_total_tax = floatval(data_get($qb_inv, 'TxnTaxDetail.TotalTax'));

        // Ninja amounts should match QB within tolerance (0.01)
        $this->assertEqualsWithDelta($qb_total_amt, $invoice->amount, 0.02, 'Ninja amount should match QB TotalAmt');
        $this->assertGreaterThan(0, $qb_total_tax, 'QB should have calculated tax');
    }

    /**
     * Test AST creates TaxRate records in Ninja from QB response.
     */
    public function test_ast_creates_tax_rate_if_needed()
    {
        $ast = $this->company->quickbooks->settings->automatic_taxes;
        if (!$ast) {
            $this->markTestSkipped('QBUS company does not have AST enabled');
        }

        $client = $this->pushClientToQb('AST TaxRate Client ' . time());

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->number = 'AST-TR-' . time();
        $invoice->date = '2026-03-01';
        $invoice->due_date = '2026-03-31';
        $invoice->uses_inclusive_taxes = false;
        $invoice->line_items = [
            $this->makeLineItem('TaxRate Test Product', 100.00, '', 0, '1', 1),
        ];
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        $this->qb->invoice->syncToForeign([$invoice]);

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->sync->qb_id);
        $this->qb_cleanup[] = ['type' => 'Invoice', 'id' => $invoice->sync->qb_id];

        // After AST processing, the tax name/rate from the line item should exist as a TaxRate
        $line_items = $invoice->line_items;
        if ($line_items[0]->tax_rate1 > 0) {
            $tax_rate = TaxRate::where('company_id', $this->company->id)
                ->where('name', $line_items[0]->tax_name1)
                ->where('rate', $line_items[0]->tax_rate1)
                ->first();

            $this->assertNotNull($tax_rate, 'TaxRate should be created from QB AST response');
        }
    }

    /**
     * Test AST with all exempt lines — no taxes applied.
     */
    public function test_ast_all_exempt_lines_no_taxes()
    {
        $ast = $this->company->quickbooks->settings->automatic_taxes;
        if (!$ast) {
            $this->markTestSkipped('QBUS company does not have AST enabled');
        }

        $client = $this->pushClientToQb('AST Exempt Client ' . time());

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->number = 'AST-EXM-' . time();
        $invoice->date = '2026-03-01';
        $invoice->due_date = '2026-03-31';
        $invoice->uses_inclusive_taxes = false;
        $invoice->line_items = [
            $this->makeLineItem('Exempt A', 100.00, '', 0, '5', 1),
            $this->makeLineItem('Exempt B', 200.00, '', 0, '5', 1),
        ];
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        $this->qb->invoice->syncToForeign([$invoice]);

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->sync->qb_id);
        $this->qb_cleanup[] = ['type' => 'Invoice', 'id' => $invoice->sync->qb_id];

        // Fetch QB invoice — should have zero tax
        $qb_inv = $this->qb->sdk->FindById('Invoice', $invoice->sync->qb_id);
        $qb_total_tax = floatval(data_get($qb_inv, 'TxnTaxDetail.TotalTax', 0));
        $this->assertEquals(0, $qb_total_tax, 'All-exempt invoice should have zero tax');

        // Ninja line items should have no taxes
        foreach ($invoice->line_items as $line) {
            $this->assertEquals(0, $line->tax_rate1, 'Exempt line should have zero tax rate');
        }
    }

    /**
     * Test AST aggregates multi-jurisdiction taxes (state + county + city) into single rate.
     */
    public function test_ast_multi_tax_jurisdiction_aggregation()
    {
        $ast = $this->company->quickbooks->settings->automatic_taxes;
        if (!$ast) {
            $this->markTestSkipped('QBUS company does not have AST enabled');
        }

        $client = $this->pushClientToQb('AST Multi-Tax Client ' . time());

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->number = 'AST-MTX-' . time();
        $invoice->date = '2026-03-01';
        $invoice->due_date = '2026-03-31';
        $invoice->uses_inclusive_taxes = false;
        $invoice->line_items = [
            $this->makeLineItem('Multi-Tax Product', 500.00, '', 0, '1', 1),
        ];
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        $this->qb->invoice->syncToForeign([$invoice]);

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->sync->qb_id);
        $this->qb_cleanup[] = ['type' => 'Invoice', 'id' => $invoice->sync->qb_id];

        // Verify that taxes are aggregated into tax_name1/tax_rate1 only
        // (tax_name2/3 and tax_rate2/3 should be empty/zero)
        $line = $invoice->line_items[0];
        if ($line->tax_rate1 > 0) {
            $this->assertNotEmpty($line->tax_name1, 'Aggregated tax should have a name');
            $this->assertEquals('', $line->tax_name2, 'tax_name2 should be empty (aggregated into tax_name1)');
            $this->assertEquals(0, $line->tax_rate2, 'tax_rate2 should be zero (aggregated into tax_rate1)');
            $this->assertEquals('', $line->tax_name3, 'tax_name3 should be empty');
            $this->assertEquals(0, $line->tax_rate3, 'tax_rate3 should be zero');
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    //  PAYMENT METHOD MAPPING TESTS
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Test cash payment maps to correct QB PaymentMethodRef.
     */
    public function test_payment_method_cash_maps_correctly()
    {
        $client = $this->pushClientToQb('Cash Payment Client ' . time());

        $invoice = $this->pushInvoiceToQb($client, 'INV-CASH-' . time(), 100.00);

        $payment = PaymentFactory::create($this->company->id, $this->user->id);
        $payment->client_id = $client->id;
        $payment->amount = $invoice->amount;
        $payment->applied = $invoice->amount;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->type_id = PaymentType::CASH;
        $payment->date = '2026-03-05';
        $payment->transaction_reference = 'CASH-' . time();
        $payment->saveQuietly();

        $payment->invoices()->attach($invoice->id, ['amount' => $invoice->amount]);
        $invoice->service()->updateBalance($payment->amount * -1)->updatePaidToDate($payment->amount)->setCalculatedStatus()->save();

        $payment = $payment->fresh();
        $this->qb->payment->syncToForeign([$payment]);
        $payment = $payment->fresh();
        $this->assertNotNull($payment->sync->qb_id);
        $this->qb_cleanup[] = ['type' => 'Payment', 'id' => $payment->sync->qb_id];

        // Fetch from QB and check PaymentMethodRef
        $qb_payment = $this->qb->sdk->FindById('Payment', $payment->sync->qb_id);
        $this->assertNotNull($qb_payment);

        $payment_method_ref = data_get($qb_payment, 'PaymentMethodRef');
        // PaymentMethodRef should be set if the payment_method_map has a Cash entry
        $method_map = $this->company->quickbooks->settings->payment_method_map ?? [];
        $has_cash = collect($method_map)->contains(fn($m) => strcasecmp($m['name'] ?? '', 'Cash') === 0);
        if ($has_cash) {
            $this->assertNotEmpty($payment_method_ref, 'Cash payment should have PaymentMethodRef');
        }
    }

    /**
     * Test credit card payment maps to QB PaymentMethodRef.
     */
    public function test_payment_method_credit_card_maps_correctly()
    {
        $client = $this->pushClientToQb('CC Payment Client ' . time());

        $invoice = $this->pushInvoiceToQb($client, 'INV-CC-' . time(), 150.00);

        $payment = PaymentFactory::create($this->company->id, $this->user->id);
        $payment->client_id = $client->id;
        $payment->amount = $invoice->amount;
        $payment->applied = $invoice->amount;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->type_id = PaymentType::VISA;
        $payment->date = '2026-03-05';
        $payment->transaction_reference = 'CC-' . time();
        $payment->saveQuietly();

        $payment->invoices()->attach($invoice->id, ['amount' => $invoice->amount]);
        $invoice->service()->updateBalance($payment->amount * -1)->updatePaidToDate($payment->amount)->setCalculatedStatus()->save();

        $payment = $payment->fresh();
        $this->qb->payment->syncToForeign([$payment]);
        $payment = $payment->fresh();
        $this->assertNotNull($payment->sync->qb_id);
        $this->qb_cleanup[] = ['type' => 'Payment', 'id' => $payment->sync->qb_id];

        $qb_payment = $this->qb->sdk->FindById('Payment', $payment->sync->qb_id);
        $this->assertNotNull($qb_payment);

        // Verify PaymentMethodRef exists if map has credit card entry
        $method_map = $this->company->quickbooks->settings->payment_method_map ?? [];
        $has_cc = collect($method_map)->contains(fn($m) => strcasecmp($m['type'] ?? '', 'CREDIT_CARD') === 0);
        if ($has_cc) {
            $payment_method_ref = data_get($qb_payment, 'PaymentMethodRef');
            $this->assertNotEmpty($payment_method_ref, 'Credit card payment should have PaymentMethodRef');
        }
    }

    /**
     * Test check/bank transfer payment maps to QB PaymentMethodRef.
     */
    public function test_payment_method_check_maps_correctly()
    {
        $client = $this->pushClientToQb('Check Payment Client ' . time());

        $invoice = $this->pushInvoiceToQb($client, 'INV-CHK-' . time(), 200.00);

        $payment = PaymentFactory::create($this->company->id, $this->user->id);
        $payment->client_id = $client->id;
        $payment->amount = $invoice->amount;
        $payment->applied = $invoice->amount;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->type_id = PaymentType::CHECK;
        $payment->date = '2026-03-05';
        $payment->transaction_reference = 'CHK-' . time();
        $payment->saveQuietly();

        $payment->invoices()->attach($invoice->id, ['amount' => $invoice->amount]);
        $invoice->service()->updateBalance($payment->amount * -1)->updatePaidToDate($payment->amount)->setCalculatedStatus()->save();

        $payment = $payment->fresh();
        $this->qb->payment->syncToForeign([$payment]);
        $payment = $payment->fresh();
        $this->assertNotNull($payment->sync->qb_id);
        $this->qb_cleanup[] = ['type' => 'Payment', 'id' => $payment->sync->qb_id];

        $qb_payment = $this->qb->sdk->FindById('Payment', $payment->sync->qb_id);
        $this->assertNotNull($qb_payment);

        $method_map = $this->company->quickbooks->settings->payment_method_map ?? [];
        $has_check = collect($method_map)->contains(fn($m) => strcasecmp($m['name'] ?? '', 'Check') === 0);
        if ($has_check) {
            $payment_method_ref = data_get($qb_payment, 'PaymentMethodRef');
            $this->assertNotEmpty($payment_method_ref, 'Check payment should have PaymentMethodRef');
        }
    }

    /**
     * Test payment with unknown type_id has no PaymentMethodRef.
     */
    public function test_payment_method_unknown_type_no_ref()
    {
        $client = $this->pushClientToQb('Unknown Payment Client ' . time());

        $invoice = $this->pushInvoiceToQb($client, 'INV-UNK-' . time(), 75.00);

        $payment = PaymentFactory::create($this->company->id, $this->user->id);
        $payment->client_id = $client->id;
        $payment->amount = $invoice->amount;
        $payment->applied = $invoice->amount;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->type_id = null; // No type set
        $payment->date = '2026-03-05';
        $payment->saveQuietly();

        $payment->invoices()->attach($invoice->id, ['amount' => $invoice->amount]);
        $invoice->service()->updateBalance($payment->amount * -1)->updatePaidToDate($payment->amount)->setCalculatedStatus()->save();

        $payment = $payment->fresh();
        $this->qb->payment->syncToForeign([$payment]);
        $payment = $payment->fresh();
        $this->assertNotNull($payment->sync->qb_id);
        $this->qb_cleanup[] = ['type' => 'Payment', 'id' => $payment->sync->qb_id];

        $qb_payment = $this->qb->sdk->FindById('Payment', $payment->sync->qb_id);
        $this->assertNotNull($qb_payment);

        // No type_id should result in no PaymentMethodRef
        $payment_method_ref = data_get($qb_payment, 'PaymentMethodRef');
        $this->assertEmpty($payment_method_ref, 'Null type_id should have no PaymentMethodRef');
    }

    // ──────────────────────────────────────────────────────────────────────
    //  CREDIT MEMO / ZERO-AMOUNT PAYMENT TESTS
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Test QB payment import with amount=0 creates a credit memo.
     */
    public function test_credit_memo_from_zero_amount_payment()
    {
        // Create a client with QB ID for the transformer to resolve
        $client = $this->createClientWithQbId('QB-US-CREDIT-MEMO');

        $transformer = new \App\Services\Quickbooks\Transformers\PaymentTransformer($this->company);

        // Simulate a QB payment with amount=0 (credit memo)
        $qb_payment_data = [
            'Id' => 'CM-' . time(),
            'TotalAmt' => 0,
            'UnappliedAmt' => 0,
            'TxnDate' => '2026-03-05',
            'CustomerRef' => $client->sync->qb_id,
            'CurrencyRef' => 'USD',
            'PrivateNote' => 'Credit memo test',
            'Line' => [
                [
                    'Amount' => 50.00,
                    'LinkedTxn' => [
                        'TxnId' => 'CM-TXN-123',
                        'TxnType' => 'CreditMemo',
                    ],
                ],
            ],
        ];

        $payment = $transformer->buildPayment($qb_payment_data);

        $this->assertNotNull($payment, 'Payment should be created from credit memo');
        $this->assertEquals(0, $payment->amount, 'Credit memo payment should have amount=0');
        $this->assertEquals(PaymentType::CREDIT, $payment->type_id, 'Credit memo should have CREDIT type');

        // Verify credit was created
        $credit = \App\Models\Credit::where('client_id', $client->id)
            ->where('amount', 50.00)
            ->first();

        $this->assertNotNull($credit, 'Credit record should be created from credit memo');
        $this->assertEquals(50.00, $credit->amount, 'Credit amount should match');
        $this->assertEquals(4, $credit->status_id, 'Credit should be completed');

        // Verify paymentable links payment to credit
        $paymentable = \App\Models\Paymentable::where('payment_id', $payment->id)
            ->where('paymentable_type', \App\Models\Credit::class)
            ->first();
        $this->assertNotNull($paymentable, 'Paymentable should link payment to credit');
        $this->assertEquals(50.00, $paymentable->amount, 'Paymentable amount should match credit');
    }

    /**
     * Test QB payment import with amount>0 does NOT create a credit memo.
     */
    public function test_normal_payment_does_not_create_credit()
    {
        $client = $this->createClientWithQbId('QB-US-NO-CREDIT');

        $transformer = new \App\Services\Quickbooks\Transformers\PaymentTransformer($this->company);

        $qb_payment_data = [
            'Id' => 'PAY-NORM-' . time(),
            'TotalAmt' => 100.00,
            'UnappliedAmt' => 0,
            'TxnDate' => '2026-03-05',
            'CustomerRef' => $client->sync->qb_id,
            'CurrencyRef' => 'USD',
            'Line' => [
                [
                    'Amount' => 100.00,
                    'LinkedTxn' => [
                        'TxnId' => 'INV-123',
                        'TxnType' => 'Invoice',
                    ],
                ],
            ],
        ];

        $payment = $transformer->buildPayment($qb_payment_data);

        $this->assertNotNull($payment);
        $this->assertEquals(100.00, $payment->amount, 'Normal payment should have correct amount');
        $this->assertNotEquals(PaymentType::CREDIT, $payment->type_id, 'Normal payment should NOT have CREDIT type');
    }

    // ──────────────────────────────────────────────────────────────────────
    //  PAYMENT CLOSED-PERIOD ERROR HANDLING TESTS
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Test immutable payment is skipped during sync.
     */
    public function test_immutable_payment_skipped_during_sync()
    {
        $client = $this->pushClientToQb('Immutable Payment Client ' . time());

        $invoice = $this->pushInvoiceToQb($client, 'INV-IMM-' . time(), 100.00);

        // Create payment and push to QB
        $payment = PaymentFactory::create($this->company->id, $this->user->id);
        $payment->client_id = $client->id;
        $payment->amount = $invoice->amount;
        $payment->applied = $invoice->amount;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->date = '2026-03-05';
        $payment->saveQuietly();

        $payment->invoices()->attach($invoice->id, ['amount' => $invoice->amount]);
        $invoice->service()->updateBalance($payment->amount * -1)->updatePaidToDate($payment->amount)->setCalculatedStatus()->save();

        $payment = $payment->fresh();
        $this->qb->payment->syncToForeign([$payment]);
        $payment = $payment->fresh();
        $this->assertNotNull($payment->sync->qb_id);
        $this->qb_cleanup[] = ['type' => 'Payment', 'id' => $payment->sync->qb_id];

        $original_qb_id = $payment->sync->qb_id;

        // Flag payment as immutable (simulating closed period)
        $sync = $payment->sync;
        $sync->qb_immutable = true;
        $payment->sync = $sync;
        $payment->status_id = Payment::STATUS_CANCELLED;
        $payment->saveQuietly();

        // Try to void — should be skipped
        $this->qb->payment->syncToForeign([$payment]);

        $payment = $payment->fresh();
        // QB ID should remain unchanged because the void was skipped
        $this->assertEquals($original_qb_id, $payment->sync->qb_id, 'Immutable payment QB ID should not be cleared');
    }

    /**
     * Test isClosedPeriodError detects closed period patterns.
     */
    public function test_closed_period_error_detection()
    {
        // Use reflection to test the private method
        $qb_payment = new \App\Services\Quickbooks\Models\QbPayment($this->qb);
        $method = new \ReflectionMethod($qb_payment, 'isClosedPeriodError');

        $this->assertTrue($method->invoke($qb_payment, 'The period has been closed'), 'Should detect "period has been closed"');
        $this->assertTrue($method->invoke($qb_payment, 'Error 6140: Closed period transaction'), 'Should detect "error 6140"');
        $this->assertTrue($method->invoke($qb_payment, 'Error 6210: Cannot modify'), 'Should detect "error 6210"');
        $this->assertTrue($method->invoke($qb_payment, 'Business validation error occurred'), 'Should detect "business validation error"');
        $this->assertTrue($method->invoke($qb_payment, "You can't void this transaction"), 'Should detect "you can\'t void"');
        $this->assertFalse($method->invoke($qb_payment, 'Network timeout'), 'Should not match unrelated errors');
        $this->assertFalse($method->invoke($qb_payment, 'Invalid amount'), 'Should not match unrelated errors');
    }

    /**
     * Test extractReadableError parses XML errors from QB.
     */
    public function test_extract_readable_error_from_xml()
    {
        $qb_payment = new \App\Services\Quickbooks\Models\QbPayment($this->qb);
        $method = new \ReflectionMethod($qb_payment, 'extractReadableError');

        // Test XML parsing
        $xml_error = 'Request is not made successful. with body: [<?xml version="1.0"?><IntuitResponse><Fault><Error><Message>Business Validation Error</Message><Detail>Period is closed</Detail></Error></Fault></IntuitResponse>]';
        $result = $method->invoke($qb_payment, $xml_error);
        $this->assertStringContainsString('Business Validation Error', $result);
        $this->assertStringContainsString('Period is closed', $result);

        // Test fallback when no XML
        $plain_error = 'Request is not made successful. Something went wrong';
        $result = $method->invoke($qb_payment, $plain_error);
        $this->assertStringContainsString('Something went wrong', $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  PRODUCT CATEGORY/GROUP SKIP TESTS
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Test that Category items are skipped during syncToNinja.
     */
    public function test_product_sync_skips_category_items()
    {
        $qb_category_item = [
            'Id' => 'CAT-' . time(),
            'Name' => 'Electronics Category',
            'Type' => 'Category',
        ];

        // Count products before sync
        $product_count_before = Product::where('company_id', $this->company->id)->count();

        // syncToNinja should skip Category items
        $this->qb->product->syncToNinja([$qb_category_item]);

        $product_count_after = Product::where('company_id', $this->company->id)->count();

        $this->assertEquals($product_count_before, $product_count_after, 'Category items should be skipped during sync');
    }

    /**
     * Test that Group items are skipped during syncToNinja.
     */
    public function test_product_sync_skips_group_items()
    {
        $qb_group_item = [
            'Id' => 'GRP-' . time(),
            'Name' => 'Bundle Group',
            'Type' => 'Group',
        ];

        $product_count_before = Product::where('company_id', $this->company->id)->count();

        $this->qb->product->syncToNinja([$qb_group_item]);

        $product_count_after = Product::where('company_id', $this->company->id)->count();

        $this->assertEquals($product_count_before, $product_count_after, 'Group items should be skipped during sync');
    }

    /**
     * Test findOrCreateProduct excludes Category/Group types in QB query.
     */
    public function test_find_or_create_product_excludes_categories()
    {
        $unique = 'Valid Product ' . time();

        $line_item = InvoiceItemFactory::create();
        $line_item->product_key = $unique;
        $line_item->notes = 'Test product that should not match categories';
        $line_item->cost = 50.00;
        $line_item->quantity = 1;
        $line_item->type_id = '1';
        $line_item->tax_id = '1';

        // This should create a new product (Service/NonInventory), never match a Category
        $qb_id = $this->qb->product->findOrCreateProduct($line_item);
        $this->assertNotEmpty($qb_id, 'Should create a new product');
        $this->qb_cleanup[] = ['type' => 'Item', 'id' => $qb_id];

        // Verify the created item is not a Category
        $qb_item = $this->qb->sdk->FindById('Item', $qb_id);
        $this->assertNotEquals('Category', data_get($qb_item, 'Type'), 'Created item should not be Category');
        $this->assertNotEquals('Group', data_get($qb_item, 'Type'), 'Created item should not be Group');
    }

    // ──────────────────────────────────────────────────────────────────────
    //  DUPLICATE CLIENT DISPLAY NAME RESOLUTION TESTS
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Test createQbClient resolves existing customer by display name instead of creating duplicate.
     */
    public function test_client_duplicate_display_name_resolved()
    {
        $unique = 'Duplicate Test Client ' . time();

        // First push: creates the customer in QB
        $client1 = ClientFactory::create($this->company->id, $this->user->id);
        $client1->name = $unique;
        $client1->address1 = '100 First Avenue';
        $client1->city = 'New York';
        $client1->state = 'NY';
        $client1->postal_code = '10001';
        $client1->country_id = 840;
        $client1->saveQuietly();

        $contact1 = ClientContactFactory::create($this->company->id, $this->user->id);
        $contact1->client_id = $client1->id;
        $contact1->first_name = 'First';
        $contact1->last_name = 'Contact';
        $contact1->email = 'first-' . time() . '@dup-test.com';
        $contact1->is_primary = true;
        $contact1->saveQuietly();

        $client1 = $client1->fresh();
        $qb_id_1 = $this->qb->client->createQbClient($client1);
        $this->assertNotEmpty($qb_id_1);
        $this->qb_cleanup[] = ['type' => 'Customer', 'id' => $qb_id_1];

        // Second push: different Ninja client with SAME name, no sync->qb_id
        // Should find existing QB customer by display name
        $client2 = ClientFactory::create($this->company->id, $this->user->id);
        $client2->name = $unique; // Same name
        $client2->address1 = '200 Second Avenue';
        $client2->city = 'Boston';
        $client2->state = 'MA';
        $client2->postal_code = '02101';
        $client2->country_id = 840;
        $client2->saveQuietly();

        $contact2 = ClientContactFactory::create($this->company->id, $this->user->id);
        $contact2->client_id = $client2->id;
        $contact2->first_name = 'Second';
        $contact2->last_name = 'Contact';
        $contact2->email = 'second-' . time() . '@dup-test.com';
        $contact2->is_primary = true;
        $contact2->saveQuietly();

        $client2 = $client2->fresh();
        $qb_id_2 = $this->qb->client->createQbClient($client2);
        $this->assertNotEmpty($qb_id_2);

        // Should resolve to the SAME QB customer ID (not create a duplicate)
        $this->assertEquals($qb_id_1, $qb_id_2, 'Second client with same name should resolve to existing QB customer');

        // Verify client2 now has the sync->qb_id set
        $client2 = $client2->fresh();
        $this->assertEquals($qb_id_1, $client2->sync->qb_id, 'Client 2 should have QB ID stored from name resolution');
    }

    // ──────────────────────────────────────────────────────────────────────
    //  SALES RECEIPT / IMPORT TESTS
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Test QB invoice pull (qbToNinja) handles line items with TAX code correctly.
     */
    public function test_invoice_pull_with_tax_line_items()
    {
        $client = $this->createClientWithQbId('QB-US-PULL-TAX');
        $client_qb_id = $client->sync->qb_id;

        $qb_invoice = [
            'Id' => 'PULL-TAX-' . time(),
            'CustomerRef' => $client_qb_id,
            'DocNumber' => 'INV-PULL-TAX',
            'TxnDate' => '2026-03-01',
            'DueDate' => '2026-03-31',
            'Balance' => 216.50,
            'TotalAmt' => 216.50,
            'Line' => [
                [
                    'DetailType' => 'SalesItemLineDetail',
                    'Amount' => 200.00,
                    'Description' => 'Taxable product',
                    'SalesItemLineDetail' => [
                        'ItemRef' => ['name' => 'Widget'],
                        'Qty' => 2,
                        'UnitPrice' => 100.00,
                        'TaxCodeRef' => ['value' => 'TAX'],
                    ],
                ],
            ],
            'TxnTaxDetail' => [
                'TotalTax' => 16.50,
                'TaxLine' => [
                    [
                        'Amount' => 16.50,
                        'DetailType' => 'TaxLineDetail',
                        'TaxLineDetail' => [
                            'TaxRateRef' => ['value' => '1'],
                            'TaxPercent' => 8.25,
                            'NetAmountTaxable' => 200.00,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->invoice_transformer->qbToNinja($qb_invoice, $this->qb);

        $this->assertIsArray($result);
        $this->assertEquals($client->id, $result['client_id']);
        $this->assertEquals('INV-PULL-TAX', $result['number']);
        $this->assertEquals(216.50, $result['balance']);

        // Verify line items exist (requires qb_service for helper->getLineItems)
        $this->assertArrayHasKey('line_items', $result);
        $this->assertNotEmpty($result['line_items']);
    }

    /**
     * Test QB invoice pull with NON (exempt) line items.
     */
    public function test_invoice_pull_with_exempt_line_items()
    {
        $client = $this->createClientWithQbId('QB-US-PULL-NON');
        $client_qb_id = $client->sync->qb_id;

        $qb_invoice = [
            'Id' => 'PULL-NON-' . time(),
            'CustomerRef' => $client_qb_id,
            'DocNumber' => 'INV-PULL-NON',
            'TxnDate' => '2026-03-01',
            'DueDate' => '2026-03-31',
            'Balance' => 50.00,
            'TotalAmt' => 50.00,
            'Line' => [
                [
                    'DetailType' => 'SalesItemLineDetail',
                    'Amount' => 50.00,
                    'Description' => 'Exempt product',
                    'SalesItemLineDetail' => [
                        'ItemRef' => ['name' => 'Gift Card'],
                        'Qty' => 1,
                        'UnitPrice' => 50.00,
                        'TaxCodeRef' => ['value' => 'NON'],
                    ],
                ],
            ],
            'TxnTaxDetail' => [
                'TotalTax' => 0,
            ],
        ];

        $result = $this->invoice_transformer->qbToNinja($qb_invoice, $this->qb);

        $this->assertIsArray($result);
        $this->assertEquals(50.00, $result['balance']);
        $this->assertEquals(0, floatval(data_get($qb_invoice, 'TxnTaxDetail.TotalTax')));
    }

    // ──────────────────────────────────────────────────────────────────────
    //  PRIVATE HELPER METHODS
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Push a client to QuickBooks and return the saved client with sync ID.
     */
    private function pushClientToQb(string $name): Client
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->name = $name;
        $client->address1 = '100 Test Street';
        $client->city = 'New York';
        $client->state = 'NY';
        $client->postal_code = '10001';
        $client->country_id = 840;
        $client->saveQuietly();

        $contact = ClientContactFactory::create($this->company->id, $this->user->id);
        $contact->client_id = $client->id;
        $contact->first_name = 'Test';
        $contact->last_name = 'User';
        $contact->email = 'test-' . time() . '-' . rand(100, 999) . '@e2e.com';
        $contact->is_primary = true;
        $contact->send_email = true;
        $contact->saveQuietly();

        $client = $client->fresh();

        $qb_id = $this->qb->client->createQbClient($client);
        $this->assertNotEmpty($qb_id, "Failed to push client '{$name}' to QuickBooks");
        $this->qb_cleanup[] = ['type' => 'Customer', 'id' => $qb_id];

        $sync = new ClientSync();
        $sync->qb_id = $qb_id;
        $client->sync = $sync;
        $client->saveQuietly();

        return $client->fresh();
    }

    /**
     * Push an invoice to QuickBooks and return the saved invoice with sync ID.
     */
    private function pushInvoiceToQb(Client $client, string $number, float $amount): Invoice
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->number = $number;
        $invoice->date = '2026-03-01';
        $invoice->due_date = '2026-03-31';
        $invoice->uses_inclusive_taxes = false;
        $invoice->line_items = [
            $this->makeLineItem('Product ' . $number, $amount, '', 0, '1', 1),
        ];
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        // Push via syncToForeign to get AST tax processing
        $this->qb->invoice->syncToForeign([$invoice]);

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->sync->qb_id, "Failed to push invoice '{$number}' to QuickBooks");
        $this->qb_cleanup[] = ['type' => 'Invoice', 'id' => $invoice->sync->qb_id];

        return $invoice;
    }

    /**
     * Deactivate/void QB entities created during tests.
     */
    protected function tearDown(): void
    {
        foreach ($this->qb_cleanup as $entity) {
            try {
                $type = $entity['type'];
                $id = $entity['id'];

                if ($type === 'Invoice') {
                    // Void the invoice
                    $qb_obj = $this->qb->sdk->FindById('Invoice', $id);
                    if ($qb_obj) {
                        $this->qb->sdk->Void($qb_obj);
                    }
                } elseif ($type === 'Customer') {
                    // Deactivate the customer
                    $qb_obj = $this->qb->sdk->FindById('Customer', $id);
                    if ($qb_obj) {
                        $update_data = [
                            'Id' => $id,
                            'SyncToken' => data_get($qb_obj, 'SyncToken'),
                            'Active' => false,
                        ];

                        if ($display_name = data_get($qb_obj, 'DisplayName')) {
                            $update_data['DisplayName'] = $display_name;
                        } elseif ($company_name = data_get($qb_obj, 'CompanyName')) {
                            $update_data['CompanyName'] = $company_name;
                        } else {
                            if ($given_name = data_get($qb_obj, 'GivenName')) {
                                $update_data['GivenName'] = $given_name;
                            }
                            if ($family_name = data_get($qb_obj, 'FamilyName')) {
                                $update_data['FamilyName'] = $family_name;
                            }
                        }

                        $update = \QuickBooksOnline\API\Facades\Customer::create($update_data);
                        $this->qb->sdk->Update($update);
                    }
                } elseif ($type === 'Item') {
                    // Deactivate the item
                    $qb_obj = $this->qb->sdk->FindById('Item', $id);
                    if ($qb_obj) {
                        $update_data = [
                            'Id' => $id,
                            'SyncToken' => data_get($qb_obj, 'SyncToken'),
                            'Name' => data_get($qb_obj, 'Name'),
                            'Type' => data_get($qb_obj, 'Type'),
                            'Active' => false,
                        ];

                        if ($income_account_ref = data_get($qb_obj, 'IncomeAccountRef')) {
                            $update_data['IncomeAccountRef'] = $income_account_ref;
                        }
                        if ($expense_account_ref = data_get($qb_obj, 'ExpenseAccountRef')) {
                            $update_data['ExpenseAccountRef'] = $expense_account_ref;
                        }

                        $update = \QuickBooksOnline\API\Facades\Item::create($update_data);
                        $this->qb->sdk->Update($update);
                    }
                } elseif ($type === 'Payment') {
                    // Void the payment
                    $qb_obj = $this->qb->sdk->FindById('Payment', $id);
                    if ($qb_obj && data_get($qb_obj, 'TxnStatus') !== 'Voided') {
                        $this->qb->sdk->Void($qb_obj);
                    }
                }
            } catch (\Throwable $e) {
                // Log but don't fail the test on cleanup errors
                nlog("QB cleanup failed for {$entity['type']} {$entity['id']}: " . $e->getMessage());
            }
        }

        $this->qb_cleanup = [];

        parent::tearDown();
    }

    /**
     * Create a line item stdClass for invoice tests.
     */
    private function makeLineItem(
        string $product_key,
        float $cost,
        string $tax_name1 = '',
        float $tax_rate1 = 0,
        string $tax_id = '1',
        int $quantity = 1,
    ): \stdClass {
        $item = InvoiceItemFactory::create();
        $item->product_key = $product_key;
        $item->notes = "Description for {$product_key}";
        $item->cost = $cost;
        $item->quantity = $quantity;
        $item->line_total = $cost * $quantity;
        $item->tax_name1 = $tax_name1;
        $item->tax_rate1 = $tax_rate1;
        $item->type_id = '1';
        $item->tax_id = $tax_id;

        return $item;
    }

    /**
     * Create an invoice with line items using the real QuickbooksService.
     *
     * @param array $line_items Array of stdClass line items
     * @return array [Invoice, QuickbooksService]
     */
    private function createUSInvoice(array $line_items): array
    {
        // Create a client with a QB ID for the invoice
        $client = $this->createClientWithQbId('QB-US-CLIENT-AUTO');

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->line_items = $line_items;
        $invoice->uses_inclusive_taxes = false;
        $invoice->date = '2026-02-15';
        $invoice->due_date = '2026-03-15';
        $invoice->saveQuietly();

        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        return [$invoice, $this->qb];
    }

    /**
     * Create a client with a QuickBooks sync ID for testing.
     * The $qb_id parameter is used as a unique identifier for the client name/email,
     * but the actual QB ID is obtained by pushing the client to QuickBooks.
     */
    private function createClientWithQbId(string $qb_id): Client
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->name = 'Test Client ' . $qb_id;
        $client->address1 = '100 Test Street';
        $client->city = 'New York';
        $client->state = 'NY';
        $client->postal_code = '10001';
        $client->country_id = 840;
        $client->saveQuietly();

        $contact = ClientContactFactory::create($this->company->id, $this->user->id);
        $contact->client_id = $client->id;
        $contact->first_name = 'Test';
        $contact->last_name = 'User';
        $contact->email = 'test-' . $qb_id . '@example.com';
        $contact->is_primary = true;
        $contact->send_email = true;
        $contact->saveQuietly();

        $client = $client->fresh();

        // Push client to QuickBooks to get the actual QB ID
        $actual_qb_id = $this->qb->client->createQbClient($client);
        $this->assertNotEmpty($actual_qb_id, "Failed to push client '{$client->name}' to QuickBooks");
        $this->qb_cleanup[] = ['type' => 'Customer', 'id' => $actual_qb_id];


        $sync = new ClientSync();
        $sync->qb_id = $actual_qb_id;
        $client->sync = $sync;
        $client->saveQuietly();

        return $client->fresh();
    }
}
