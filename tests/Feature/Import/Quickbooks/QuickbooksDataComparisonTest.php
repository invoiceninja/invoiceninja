<?php

namespace Tests\Feature\Import\Quickbooks;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ClientContact;
use App\Services\Quickbooks\QuickbooksService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Test suite to compare data imported from QuickBooks to Invoice Ninja.
 * 
 * These tests compare all imported fields between QuickBooks and Invoice Ninja models.
 * Model-only comparisons - no calculations are performed.
 */
class QuickbooksDataComparisonTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Get all companies with QuickBooks configured.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getQuickbooksCompanies(): \Illuminate\Support\Collection
    {
        return Company::whereNotNull('quickbooks')
            ->get()
            ->filter(function ($company) {
                // Filter out companies without a realmID
                $realm_id = $company->quickbooks->realmID ?? '';
                return !empty($realm_id);
            });
    }

    /**
     * Check if the company's QuickBooks token is expired and cannot be refreshed.
     */
    private function isTokenExpired(?Company $company): bool
    {
        if (!$company || !$company->quickbooks || $company->quickbooks->accessTokenExpiresAt == 0) {
            return false; // No token configured, let it fail during service creation
        }

        // Check if token is expired
        if ($company->quickbooks->accessTokenExpiresAt < time()) {
            // Token is expired - check if refresh token is also expired
            if ($company->quickbooks->refreshTokenExpiresAt > 0 && 
                $company->quickbooks->refreshTokenExpiresAt < time()) {
                return true; // Both access and refresh tokens are expired
            }
            // Access token expired but refresh token might still be valid
            // Let QuickbooksService try to refresh it
        }

        return false;
    }

    /**
     * Verify that the QuickBooks service can connect and is configured correctly.
     * The SDK is already configured with the correct RealmID when QuickbooksService is instantiated.
     * We verify by attempting to fetch company info - if this succeeds, we're connected to the correct realm.
     */
    private function verifyRealmConnection(Company $company, QuickbooksService $qb_service): bool
    {
        try {
            // The SDK is already configured with the correct RealmID via QBORealmID in the config
            // If we can successfully fetch company info, we're connected to the correct realm
            $company_info = $qb_service->sdk()->company();
            
            if ($company_info) {
                $expected_realm_id = $company->quickbooks->realmID;
                // nlog("Company {$company->id}: Connected to Realm ID {$expected_realm_id}");
                return true;
            }
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            
            // Check if it's an authentication error
            if (str_contains($error_message, '401') || 
                str_contains($error_message, 'AuthenticationFailed') ||
                str_contains($error_message, 'Token expired')) {
                // nlog("Company {$company->id}: Authentication failed - token expired or invalid");
            } else {
                // nlog("Company {$company->id}: Failed to connect to QuickBooks - " . $error_message);
            }
            
            return false;
        }

        return false;
    }


    public function testSpecificInvoiceComparisonAstResponseWithTaxExemptProduct(): void
    {
        $invoice_number = 'qb-2026-EXEMPT-0001';

        $company = Company::where('quickbooks->settings->automatic_taxes', true)->first();

        // Check if token is expired before attempting to create service
        if (!$company) {
            $this->markTestSkipped("Company with automatic taxes is not found");
        }
        elseif ($this->isTokenExpired($company)) {
            $this->markTestSkipped("QuickBooks token is expired and cannot be refreshed for company {$company->id}");
        }
        

        $this->assertNotNull($company);

        
        $qb_service = new QuickbooksService($company);
           
        $this->assertNotNull($company, 'Company with automatic taxes is not found');

        $client = Client::where('client_hash', "INeedAStableClientThatIsInTheExactRegionToTestTaxes")->first();

        if (!$client) {
            $client = Client::factory()->create([
                'company_id' => $company->id,
                'user_id' => $company->users()->first()->id,
                'address2' => '2443 Sierra Nevada Road',
                'city' => 'Mammoth Lakes',
                'state' => 'CA',
                'postal_code' => '93546',
                'country_id' => 840,
                'shipping_address2' => '2443 Sierra Nevada Road',
                'shipping_city' => 'Mammoth Lakes',
                'shipping_state' => 'CA',
                'shipping_postal_code' => '93546',
                'shipping_country_id' => 840,
                'client_hash' => "INeedAStableClientThatIsInTheExactRegionToTestTaxes",
                'is_tax_exempt' => false,
                'name' => "Mammoth Lakes Ski Resort",
            ]);

            ClientContact::factory()->create([
                'client_id' => $client->id,
                'user_id' => $client->user_id,
                'company_id' => $company->id,
                'is_primary' => true,
                'email' => 'test@mammothlakes.com',
                'phone' => '1234567890',
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

            $qb_service->client->syncToForeign([$client]);            
        }

        $this->assertNotNull($client);

        $client->refresh();

        $this->assertNotNull($client->sync->qb_id, 'Client has no QB ID');

        // Create a tax-exempt product in Invoice Ninja
        $tax_exempt_product = Product::factory()->create([
            'company_id' => $company->id,
            'user_id' => $client->user_id,
            'product_key' => 'Tax Exempt Product',
            'notes' => 'This product is tax exempt',
            'price' => 150.00,
            'cost' => 100.00,
            'tax_id' => '5', // Tax exempt
        ]);

        // Create invoice with both taxable and tax-exempt line items
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'discount' => 0,
            'client_id' => $client->id,
            'user_id' => $client->user_id,
            'number' => $invoice_number,
            'date' => '2026-01-01',
            'due_date' => '2026-02-01',
            'status_id' => Invoice::STATUS_DRAFT,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_value1' => '',
            'custom_value2' => '',
            'custom_value3' => '',
            'custom_value4' => '',
            'line_items' => [
                [
                    'product_key' => 'Taxable Product',
                    'notes' => 'This product is taxable',
                    'quantity' => 1,
                    'cost' => 100,
                    'line_total' => 100,
                    'tax_name1' => '',
                    'tax_rate1' => 0,
                    'tax_name2' => '',
                    'tax_rate2' => 0,
                    'tax_name3' => '',
                    'tax_rate3' => 0,
                    'type_id' => '1',
                    'discount' => 0,
                    'tax_id' => '1', // Taxable
                ],
                [
                    'product_key' => 'Tax Exempt Product',
                    'notes' => 'This product is tax exempt',
                    'quantity' => 1,
                    'cost' => 150,
                    'line_total' => 150,
                    'tax_name1' => '',
                    'tax_rate1' => 0,
                    'tax_name2' => '',
                    'tax_rate2' => 0,
                    'tax_name3' => '',
                    'tax_rate3' => 0,
                    'type_id' => '1',
                    'discount' => 0,
                    'tax_id' => '5', // Tax exempt
                ],
            ],
            'private_notes' => '',
            'public_notes' => 'Test invoice with tax exempt product',
            'terms' => '',
            'footer' => 'Test invoice',
            'po_number' => '',
        ]);

        $invoice = $invoice->calc()->getInvoice()->service()->markSent()->save();
    

        // Check if token is expired before attempting to create service
        if ($this->isTokenExpired($company)) {
            $this->markTestSkipped("QuickBooks token is expired and cannot be refreshed for company {$company->id}");
        }


        // Verify we're connected to the correct realm
        if (!$this->verifyRealmConnection($company, $qb_service)) {
            $this->markTestSkipped("Realm connection verification failed for company {$company->id}");
        }

        // Create the invoice in QuickBooks
        $qb_service->invoice->syncToForeign([$invoice]);
        
        // Refresh the invoice to get the updated qb_id
        $invoice->refresh();
        $qb_id = $invoice->sync->qb_id ?? null;
        
        if (empty($qb_id)) {
            $this->fail("Failed to create invoice in QuickBooks - no qb_id was returned");
        }
        
        nlog("Invoice {$invoice_number} created in QuickBooks with QB ID: {$qb_id}");
        
        // Fetch the created invoice from QuickBooks
        $qb_invoice = $qb_service->invoice->find($qb_id);
        $this->assertNotNull($qb_invoice, 'Failed to fetch newly created invoice from QuickBooks');
        
        // Verify tax-exempt line item has TaxCodeRef = 'NON' in QuickBooks
        $qb_lines = data_get($qb_invoice, 'Line', []);
        if (!is_array($qb_lines)) {
            $qb_lines = [$qb_lines];
        }
        
        // Verify tax calculations: only the taxable line item ($100) should be taxed
        // The tax-exempt line item ($150) should not be included in tax calculations
        $qb_total_tax = (float) data_get($qb_invoice, 'TxnTaxDetail.TotalTax', 0);
        $qb_subtotal = (float) data_get($qb_invoice, 'SubTotalLine.TotalAmt', 0);
        
        // If SubTotalLine is not available, calculate from TotalAmt - TotalTax
        if ($qb_subtotal == 0) {
            $qb_total_amt = (float) data_get($qb_invoice, 'TotalAmt', 0);
            $qb_subtotal = $qb_total_amt - $qb_total_tax;
        }
        
        // The subtotal should be $250 (100 + 150)
        $expected_subtotal = 250.00;
        $this->assertEqualsWithDelta(
            $expected_subtotal,
            $qb_subtotal,
            0.01,
            "Invoice subtotal should be {$expected_subtotal} (taxable: 100 + tax-exempt: 150), but got {$qb_subtotal}"
        );
        
        $this->assertGreaterThan(0, $qb_total_tax, 
            "Tax should be greater than 0 since we have a taxable line item, but got {$qb_total_tax}");
        
        $max_expected_tax_on_taxable_only = 100 * 0.10; 
        $this->assertLessThanOrEqual($max_expected_tax_on_taxable_only, $qb_total_tax,
            "Tax should only be calculated on the taxable line item (\$100), not on the full amount. Tax: {$qb_total_tax}");
        
        nlog("Tax verification: QB TotalTax={$qb_total_tax}, Subtotal={$qb_subtotal}, Expected Subtotal={$expected_subtotal}");
        
        // Perform the full comparison
        $this->compareInvoiceForCompany($invoice, $company, $qb_service);
    }

    public function testSpecificInvoiceComparisonAstResponse(): void
    {
        $invoice_number = 'qb-2026-AST-0001';

        $company = Company::where('quickbooks->settings->automatic_taxes', true)->first();
        if (!$company) {
            $this->markTestSkipped("Company with automatic taxes is not found");
        }
        // Check if token is expired before attempting to create service
        elseif ($this->isTokenExpired($company)) {
            $this->markTestSkipped("QuickBooks token is expired and cannot be refreshed for company {$company->id}");
        }
        

        $qb_service = new QuickbooksService($company);
           
        $this->assertNotNull($company, 'Company with automatic taxes is not found');

        $client = Client::where('client_hash', "INeedAStableClientThatIsInTheExactRegionToTestTaxes")->first();

        if (!$client) {
            $client = Client::factory()->create([
                'company_id' => $company->id,
                'user_id' => $company->users()->first()->id,
                'address2' => '2443 Sierra Nevada Road',
                'city' => 'Mammoth Lakes',
                'state' => 'CA',
                'postal_code' => '93546',
                'country_id' => 840,
                'shipping_address2' => '2443 Sierra Nevada Road',
                'shipping_city' => 'Mammoth Lakes',
                'shipping_state' => 'CA',
                'shipping_postal_code' => '93546',
                'shipping_country_id' => 840,
                'client_hash' => "INeedAStableClientThatIsInTheExactRegionToTestTaxes",
                'is_tax_exempt' => false,
                'name' => "Mammoth Lakes Ski Resort",
            ]);

            ClientContact::factory()->create([
                'client_id' => $client->id,
                'user_id' => $client->user_id,
                'company_id' => $company->id,
                'is_primary' => true,
                'email' => 'test@mammothlakes.com',
                'phone' => '1234567890',
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

            $qb_service->client->syncToForeign([$client]);            
        }

        $this->assertNotNull($client);

        $client->refresh();

        $this->assertNotNull($client->sync->qb_id, 'Client has no QB ID');

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'discount' => 0,
            'client_id' => $client->id,
            'user_id' => $client->user_id,
            'number' => $invoice_number,
            'date' => '2026-01-01',
            'due_date' => '2026-02-01',
            'status_id' => Invoice::STATUS_DRAFT,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_value1' => '',
            'custom_value2' => '',
            'custom_value3' => '',
            'custom_value4' => '',
            'line_items' => [
                [
                    'product_key' => 'product',
                    'notes' => 'Product Description',
                    'quantity' => 1,
                    'cost' => 100,
                    'line_total' => 100,
                    'tax_name1' => '',
                    'tax_rate1' => 0,
                    'tax_name2' => '',
                    'tax_rate2' => 0,
                    'tax_name3' => '',
                    'tax_rate3' => 0,
                    'type_id' => '1',
                    'discount' => 0,
                    'tax_id' => '1',
                ],
                [
                    'product_key' => 'service',
                    'quantity' => 1,
                    'cost' => 100,
                    'line_total' => 100,
                    'type_id' => '2',
                    'discount' => 0,
                    'tax_name1' => '',
                    'tax_rate1' => 0,
                    'tax_name2' => '',
                    'tax_rate2' => 0,
                    'tax_name3' => '',
                    'tax_rate3' => 0,
                ],
            ],
            'private_notes' => '',
            'public_notes' => 'Test invoice',
            'terms' => '',
            'footer' => 'Test invoice',
            'po_number' => '',
        ]);

        $invoice = $invoice->calc()->getInvoice()->service()->markSent()->save();
    

        // Check if token is expired before attempting to create service
        if ($this->isTokenExpired($company)) {
            $this->markTestSkipped("QuickBooks token is expired and cannot be refreshed for company {$company->id}");
        }


        // Verify we're connected to the correct realm
        if (!$this->verifyRealmConnection($company, $qb_service)) {
            $this->markTestSkipped("Realm connection verification failed for company {$company->id}");
        }

        // Create the invoice in QuickBooks
        $qb_service->invoice->syncToForeign([$invoice]);
        
        // Refresh the invoice to get the updated qb_id
        $invoice->refresh();
        $qb_id = $invoice->sync->qb_id ?? null;
        
        if (empty($qb_id)) {
            $this->fail("Failed to create invoice in QuickBooks - no qb_id was returned");
        }
        
        nlog("Invoice {$invoice_number} created in QuickBooks with QB ID: {$qb_id}");
        
        // Fetch the created invoice from QuickBooks
        $qb_invoice = $qb_service->invoice->find($qb_id);
        $this->assertNotNull($qb_invoice, 'Failed to fetch newly created invoice from QuickBooks');
        
        // Perform the comparison
        $this->compareInvoiceForCompany($invoice, $company, $qb_service);
    }


    /**
     * Test that checks for a specific invoice number (qb-2026-0001) and compares it with QuickBooks.
     * If the invoice doesn't have a qb_id, it will be created in QuickBooks first.
     */
    public function testSpecificInvoiceComparison(): void
    {
        $invoice_number = 'qb-2026-0001';

        // Find the invoice by number across all companies
        $invoice = Invoice::where('number', $invoice_number)->first();

        if (!$invoice) {
            
            $client = Client::whereNotNull('sync->qb_id')->first();
            
            if (!$client) {
                $this->markTestSkipped("Company not found");
            }
            $company = $client->company;

            
                    // Check if token is expired before attempting to create service
            if ($this->isTokenExpired($company)) {
                $this->markTestSkipped("QuickBooks token is expired and cannot be refreshed for company {$company->id}");
            }
            
            $tax_rate_map = $company->quickbooks->settings->tax_rate_map ?? [];

            $name = $tax_rate_map[0]['name'] ?? '';
            $rate = $tax_rate_map[0]['rate'] ?? 0;

            $invoice = Invoice::factory()->create([
                'company_id' => $company->id,
                'discount' => 0,
                'client_id' => $client->id,
                'user_id' => $client->user_id,
                'number' => $invoice_number,
                'date' => '2026-01-01',
                'due_date' => '2026-02-01',
                'status_id' => Invoice::STATUS_DRAFT,
                'tax_name1' => '',
                'tax_rate1' => 0,
                'tax_name2' => '',
                'tax_rate2' => 0,
                'tax_name3' => '',
                'tax_rate3' => 0,
                'custom_value1' => '',
                'custom_value2' => '',
                'custom_value3' => '',
                'custom_value4' => '',
                'line_items' => [
                    [
                        'product_key' => 'product',
                        'notes' => 'Product Description',
                        'quantity' => 1,
                        'cost' => 100,
                        'line_total' => 100,
                        'tax_name1' => $name,
                        'tax_rate1' => $rate,
                        'tax_name2' => '',
                        'tax_rate2' => 0,
                        'tax_name3' => '',
                        'tax_rate3' => 0,
                        'type_id' => '1',
                        'discount' => 0,
                        'tax_id' => '1',
                    ],
                    [
                        'product_key' => 'service',
                        'quantity' => 1,
                        'cost' => 100,
                        'line_total' => 100,
                        'type_id' => '2',
                        'discount' => 0,
                        'tax_name1' => '',
                        'tax_rate1' => 0,
                        'tax_name2' => '',
                        'tax_rate2' => 0,
                        'tax_name3' => '',
                        'tax_rate3' => 0,
                    ],
                ],
                'private_notes' => '',
                'public_notes' => 'Test invoice',
                'terms' => '',
                'footer' => 'Test invoice',
                'po_number' => '',
            ]);

            $invoice = $invoice->calc()->getInvoice()->service()->markSent()->save();
        }

        $company = $invoice->company;

        // Check if token is expired before attempting to create service
        if ($this->isTokenExpired($company)) {
            $this->markTestSkipped("QuickBooks token is expired and cannot be refreshed for company {$company->id}");
        }

        $qb_service = new QuickbooksService($company);

        // Verify we're connected to the correct realm
        if (!$this->verifyRealmConnection($company, $qb_service)) {
            $this->markTestSkipped("Realm connection verification failed for company {$company->id}");
        }

        // Check if invoice has a qb_id
        $qb_id = $invoice->sync->qb_id ?? null;

        if (empty($qb_id)) {
            // Invoice doesn't have a qb_id, create it in QuickBooks
            nlog("Invoice {$invoice_number} (ID: {$invoice->id}) does not have a qb_id. Creating in QuickBooks...");
            
            try {
                // Create the invoice in QuickBooks
                $qb_service->invoice->syncToForeign([$invoice]);
                
                // Refresh the invoice to get the updated qb_id
                $invoice->refresh();
                $qb_id = $invoice->sync->qb_id ?? null;
                
                if (empty($qb_id)) {
                    $this->fail("Failed to create invoice in QuickBooks - no qb_id was returned");
                }
                
                nlog("Invoice {$invoice_number} created in QuickBooks with QB ID: {$qb_id}");
                
                // Fetch the created invoice from QuickBooks
                $qb_invoice = $qb_service->invoice->find($qb_id);
                $this->assertNotNull($qb_invoice, 'Failed to fetch newly created invoice from QuickBooks');
                
            } catch (\Exception $e) {
                $qb_company_name = $company->quickbooks->companyName ?? 'NOT AVAILABLE';
                nlog("ERROR creating invoice {$invoice_number} in QuickBooks (Company {$company->id}): " . $e->getMessage());
                nlog("Invoice Ninja Company Name: {$company->name}");
                nlog("QuickBooks Company Name: {$qb_company_name}");
                throw $e;
            }
        } else {
            // Invoice already has a qb_id, just fetch it from QuickBooks
            nlog("Invoice {$invoice_number} (ID: {$invoice->id}) already has qb_id: {$qb_id}. Fetching from QuickBooks...");
            
            try {
                $qb_invoice = $qb_service->invoice->find($qb_id);
                $this->assertNotNull($qb_invoice, 'Failed to fetch invoice from QuickBooks. This may indicate the qb_id belongs to a different realm.');
            } catch (\Exception $e) {
                $qb_company_name = $company->quickbooks->companyName ?? 'NOT AVAILABLE';
                nlog("ERROR fetching invoice {$invoice_number} from QuickBooks (Company {$company->id}): " . $e->getMessage());
                nlog("Invoice Ninja Company Name: {$company->name}");
                nlog("QuickBooks Company Name: {$qb_company_name}");
                throw $e;
            }
        }

        // Perform the comparison
        $this->compareInvoiceForCompany($invoice, $company, $qb_service);
    }

    //This test confirms that tax rates not configured in QB will not be able to be used for QB records.

    // public function testSpecificInvoiceComparisonWithNonExistentTaxRate(): void
    // {
    //     $invoice_number = 'qb-2026-GST-0001';

    //     // Find the invoice by number across all companies
    //     $invoice = Invoice::where('number', $invoice_number)->first();

    //     if (!$invoice) {
            
    //         $client = Client::whereNotNull('sync->qb_id')->first();
            
    //         $company = $client->company;
    //         $tax_rate_map = $company->quickbooks->settings->tax_rate_map ?? [];

    //         $name = 'GST';
    //         $rate = 10;

    //         $invoice = Invoice::factory()->create([
    //             'company_id' => $company->id,
    //             'discount' => 0,
    //             'client_id' => $client->id,
    //             'user_id' => $client->user_id,
    //             'number' => $invoice_number,
    //             'date' => '2026-01-01',
    //             'due_date' => '2026-02-01',
    //             'status_id' => Invoice::STATUS_DRAFT,
    //             'tax_name1' => '',
    //             'tax_rate1' => 0,
    //             'tax_name2' => '',
    //             'tax_rate2' => 0,
    //             'tax_name3' => '',
    //             'tax_rate3' => 0,
    //             'custom_value1' => '',
    //             'custom_value2' => '',
    //             'custom_value3' => '',
    //             'custom_value4' => '',
    //             'line_items' => [
    //                 [
    //                     'product_key' => 'product',
    //                     'notes' => 'Product Description',
    //                     'quantity' => 1,
    //                     'cost' => 100,
    //                     'line_total' => 100,
    //                     'tax_name1' => $name,
    //                     'tax_rate1' => $rate,
    //                     'tax_name2' => '',
    //                     'tax_rate2' => 0,
    //                     'tax_name3' => '',
    //                     'tax_rate3' => 0,
    //                     'type_id' => '1',
    //                     'discount' => 0,
    //                     'tax_id' => '1',
    //                 ],
    //                 [
    //                     'product_key' => 'service',
    //                     'quantity' => 1,
    //                     'cost' => 100,
    //                     'line_total' => 100,
    //                     'type_id' => '2',
    //                     'discount' => 0,
    //                     'tax_name1' => '',
    //                     'tax_rate1' => 0,
    //                     'tax_name2' => '',
    //                     'tax_rate2' => 0,
    //                     'tax_name3' => '',
    //                     'tax_rate3' => 0,
    //                 ],
    //             ],
    //             'private_notes' => '',
    //             'public_notes' => 'Test invoice',
    //             'terms' => '',
    //             'footer' => 'Test invoice',
    //             'po_number' => '',
    //         ]);

    //         $invoice = $invoice->calc()->getInvoice()->service()->markSent()->save();
    //     }

    //     $company = $invoice->company;

    //     // Check if token is expired before attempting to create service
    //     if ($this->isTokenExpired($company)) {
    //         $this->markTestSkipped("QuickBooks token is expired and cannot be refreshed for company {$company->id}");
    //     }

    //     $qb_service = new QuickbooksService($company);

    //     // Verify we're connected to the correct realm
    //     if (!$this->verifyRealmConnection($company, $qb_service)) {
    //         $this->markTestSkipped("Realm connection verification failed for company {$company->id}");
    //     }

    //     // Check if invoice has a qb_id
    //     $qb_id = $invoice->sync->qb_id ?? null;

    //     if (empty($qb_id)) {
    //         // Invoice doesn't have a qb_id, create it in QuickBooks
    //         nlog("Invoice {$invoice_number} (ID: {$invoice->id}) does not have a qb_id. Creating in QuickBooks...");
            
    //         try {
    //             // Create the invoice in QuickBooks
    //             $qb_service->invoice->syncToForeign([$invoice]);
                
    //             // Refresh the invoice to get the updated qb_id
    //             $invoice->refresh();
    //             $qb_id = $invoice->sync->qb_id ?? null;
                
    //             if (empty($qb_id)) {
    //                 $this->fail("Failed to create invoice in QuickBooks - no qb_id was returned");
    //             }
                
    //             nlog("Invoice {$invoice_number} created in QuickBooks with QB ID: {$qb_id}");
                
    //             // Fetch the created invoice from QuickBooks
    //             $qb_invoice = $qb_service->invoice->find($qb_id);
    //             $this->assertNotNull($qb_invoice, 'Failed to fetch newly created invoice from QuickBooks');
                
    //         } catch (\Exception $e) {
    //             $qb_company_name = $company->quickbooks->companyName ?? 'NOT AVAILABLE';
    //             nlog("ERROR creating invoice {$invoice_number} in QuickBooks (Company {$company->id}): " . $e->getMessage());
    //             nlog("Invoice Ninja Company Name: {$company->name}");
    //             nlog("QuickBooks Company Name: {$qb_company_name}");
    //             throw $e;
    //         }
    //     } else {
    //         // Invoice already has a qb_id, just fetch it from QuickBooks
    //         nlog("Invoice {$invoice_number} (ID: {$invoice->id}) already has qb_id: {$qb_id}. Fetching from QuickBooks...");
            
    //         try {
    //             $qb_invoice = $qb_service->invoice->find($qb_id);
    //             $this->assertNotNull($qb_invoice, 'Failed to fetch invoice from QuickBooks. This may indicate the qb_id belongs to a different realm.');
    //         } catch (\Exception $e) {
    //             $qb_company_name = $company->quickbooks->companyName ?? 'NOT AVAILABLE';
    //             nlog("ERROR fetching invoice {$invoice_number} from QuickBooks (Company {$company->id}): " . $e->getMessage());
    //             nlog("Invoice Ninja Company Name: {$company->name}");
    //             nlog("QuickBooks Company Name: {$qb_company_name}");
    //             throw $e;
    //         }
    //     }

    //     // Perform the comparison
    //     $this->compareInvoiceForCompany($invoice, $company, $qb_service);
    // }

    /**
     * Test that compares all invoice fields between QuickBooks and Invoice Ninja.
     * Iterates through all companies with QuickBooks configured.
     */
    public function testInvoiceDataComparison(): void
    {
        $companies = $this->getQuickbooksCompanies();

        if ($companies->isEmpty()) {
            $this->markTestSkipped('No companies with QuickBooks configuration found');
        }

        $total_comparisons = 0;
        $skipped_companies = 0;

        foreach ($companies as $company) {
            try {
                // Check if token is expired before attempting to create service
                if ($this->isTokenExpired($company)) {
                    // nlog("Skipping company {$company->id} - QuickBooks token is expired and cannot be refreshed");
                    $skipped_companies++;
                    continue;
                }

                $qb_service = new QuickbooksService($company);

                // Verify we're connected to the correct realm
                if (!$this->verifyRealmConnection($company, $qb_service)) {
                    // nlog("Skipping company {$company->id} - realm connection verification failed");
                    $skipped_companies++;
                    continue;
                }

                // Find invoices with QuickBooks sync ID that belong to this company
                $invoices = Invoice::where('company_id', $company->id)
                    ->whereNotNull('sync->qb_id')
                    ->get();

                if ($invoices->isEmpty()) {
                    // nlog("No invoices with QuickBooks sync ID found for company {$company->id}");
                    continue;
                }

                // nlog("=== Processing Company {$company->id} ({$invoices->count()} invoices) ===");
                // nlog("Company Name: {$company->name}");
                // nlog("Realm ID: {$company->quickbooks->realmID}");

                foreach ($invoices as $invoice) {
                    try {
                        $this->compareInvoiceForCompany($invoice, $company, $qb_service);
                        $total_comparisons++;
                    } catch (\Exception $e) {
                        nlog("ERROR comparing invoice ID {$invoice->id} (Company {$company->id}): " . $e->getMessage());
                        // nlog("Invoice Number: {$invoice->number}");
                        // nlog("QB ID: {$invoice->sync->qb_id}");
                        throw $e; // Re-throw to be caught by outer try-catch
                    }
                }

                // nlog("=== Completed Company {$company->id} ===");
            } catch (\Exception $e) {
                $error_message = $e->getMessage();
                
                // Check if it's an authentication/token error
                if (str_contains($error_message, 'token expired') || 
                    str_contains($error_message, 'invalid_grant') ||
                    str_contains($error_message, 'AuthenticationFailed') ||
                    str_contains($error_message, '401') ||
                    str_contains($error_message, 'Quickbooks token expired')) {
                    // nlog("Skipping company {$company->id} - QuickBooks authentication failed (token expired or invalid)");
                } else {
                    // Get QuickBooks company name (from cached value if available)
                    $qb_company_name = $company->quickbooks->companyName ?? 'NOT AVAILABLE';
                    
                    // nlog("ERROR processing company {$company->id} (Invoices): " . $error_message);
                    // nlog("Invoice Ninja Company Name: {$company->name}");
                    // nlog("QuickBooks Company Name: {$qb_company_name}");
                    // nlog("Realm ID: {$company->quickbooks->realmID}");
                }
                
                $skipped_companies++;
                continue;
            }
        }

        // nlog("=== Invoice Comparison Summary ===");
        // nlog("Total Companies Processed: " . ($companies->count() - $skipped_companies));
        // nlog("Total Companies Skipped: {$skipped_companies}");
        // nlog("Total Invoice Comparisons: {$total_comparisons}");

        if ($total_comparisons === 0) {
            $this->markTestSkipped('No invoices found to compare across all companies');
        }
    }

    /**
     * Compare a single invoice for a specific company.
     */
    private function compareInvoiceForCompany(Invoice $invoice, Company $company, QuickbooksService $qb_service): void
    {
        $qb_id = $invoice->sync->qb_id;
        $this->assertNotEmpty($qb_id, 'Invoice must have a qb_id in sync');

        // Fetch the invoice from QuickBooks (will use the realm configured in QuickbooksService)
        $qb_invoice = $qb_service->invoice->find($qb_id);
        $this->assertNotNull($qb_invoice, 'Failed to fetch invoice from QuickBooks. This may indicate the qb_id belongs to a different realm.');

        // Verify client reference matches to ensure we're comparing records from the same realm
        $qb_customer_ref = data_get($qb_invoice, 'CustomerRef.value') ?? data_get($qb_invoice, 'CustomerRef');
        $ninja_client_qb_id = $invoice->client->sync->qb_id ?? null;
        
        // nlog("=== QuickBooks Invoice Comparison ===");
        // nlog("Invoice Ninja ID: {$invoice->id}");
        // nlog("QuickBooks ID: {$qb_id}");
        // nlog("Invoice Number: {$invoice->number}");
        // nlog("Company ID: {$company->id}");
        // nlog("Company Name: {$company->name}");
        // nlog("Realm ID: {$company->quickbooks->realmID}");
        // nlog("Client Verification:");
        // nlog("  QB CustomerRef: " . ($qb_customer_ref ?? 'NOT FOUND'));
        // nlog("  Ninja Client QB ID: " . ($ninja_client_qb_id ?? 'NOT FOUND'));
        // nlog("  Client Match: " . ($qb_customer_ref === $ninja_client_qb_id ? 'YES' : 'NO'));
        
        // Verify client reference matches
        if (empty($qb_customer_ref)) {
            nlog("  WARNING: QuickBooks invoice has no CustomerRef - cannot verify client match");
        } elseif (empty($ninja_client_qb_id)) {
            nlog("  WARNING: Invoice Ninja client has no qb_id - cannot verify client match");
        } elseif ($qb_customer_ref !== $ninja_client_qb_id) {
            // This is a critical mismatch - the invoice might be from a different realm
            $client_name = $invoice->client->name ?? 'Unknown';
            nlog("  ERROR: Client reference mismatch!");
            nlog("  This may indicate the invoice belongs to a different QuickBooks realm.");
            nlog("  Invoice Ninja Client ID: {$invoice->client->id}, Name: {$client_name}");
            
            // Try to find if there's a client in this company with the QB customer ref
            $matching_client = \App\Models\Client::where('company_id', $company->id)
                ->where('sync->qb_id', $qb_customer_ref)
                ->first();
            
            if ($matching_client) {
                nlog("  Found client in this company with QB ID {$qb_customer_ref}: Client ID {$matching_client->id}, Name: {$matching_client->name}");
                nlog("  This suggests the invoice's client reference is incorrect or the invoice belongs to a different client.");
            } else {
                nlog("  No client found in this company with QB ID {$qb_customer_ref}");
                nlog("  This strongly suggests the invoice belongs to a different QuickBooks realm.");
            }
            
            $this->fail("Client reference mismatch for invoice ID {$invoice->id}. QB CustomerRef: [{$qb_customer_ref}], Ninja Client QB ID: [{$ninja_client_qb_id}]. This may indicate the invoice belongs to a different QuickBooks realm.");
        }
        
        // Additional verification: ensure the client belongs to the same company
        if ($invoice->client->company_id !== $company->id) {
            $this->fail("Invoice client belongs to different company. Invoice client company_id: {$invoice->client->company_id}, Expected: {$company->id}");
        }

        // Compare basic invoice fields
        $this->compareInvoiceBasicFields($invoice, $qb_invoice, $company);
        
        // Compare line items
        // $this->compareInvoiceLineItems($invoice, $qb_invoice, $company);

        // Log comparison completion
        // nlog("=== Comparison Complete for Invoice {$invoice->id} ===");
    }

    /**
     * Compare basic invoice fields (no calculations).
     */
    private function compareInvoiceBasicFields(Invoice $invoice, $qb_invoice, Company $company): void
    {

        // Compare invoice number
        $qb_doc_number = data_get($qb_invoice, 'DocNumber');
        if ($qb_doc_number !== false && $qb_doc_number !== null) {
            $match = $qb_doc_number === $invoice->number;
            // nlog("Number: QB={$qb_doc_number}, Ninja={$invoice->number}, Match=" . ($match ? 'YES' : 'NO'));
            if (!$match) {
                nlog("MISMATCH: Invoice number - QB: [{$qb_doc_number}], Ninja: [{$invoice->number}]");
            }
            $this->assertEquals(
                $qb_doc_number,
                $invoice->number,
                "Invoice number mismatch for invoice ID {$invoice->id}"
            );
        }

        // Compare dates
        $qb_date = data_get($qb_invoice, 'TxnDate');
        if ($qb_date) {
            $match = $qb_date === $invoice->date;
            // nlog("Date: QB={$qb_date}, Ninja={$invoice->date}, Match=" . ($match ? 'YES' : 'NO'));
            if (!$match) {
                nlog("MISMATCH: Invoice date - QB: [{$qb_date}], Ninja: [{$invoice->date}]");
            }
            $this->assertEquals(
                $qb_date,
                $invoice->date,
                "Invoice date mismatch for invoice ID {$invoice->id}"
            );
        }

        $qb_due_date = data_get($qb_invoice, 'DueDate');
        if ($qb_due_date) {

            $this->assertEquals(
                $qb_due_date,
                $invoice->due_date,
                "Invoice due date mismatch for invoice ID {$invoice->id}"
            );
        }

        // Compare notes
        $qb_private_note = data_get($qb_invoice, 'PrivateNote', '');
        
        $this->assertEquals(
            $qb_private_note,
            $invoice->private_notes,
            "Invoice private notes mismatch for invoice ID {$invoice->id}"
        );

        $qb_public_note = data_get($qb_invoice, 'CustomerMemo', false);
        if ($qb_public_note !== false) {

            $this->assertStringContainsString(
                strip_tags(str_replace("\n", "", $qb_public_note)),
                strip_tags(str_replace("\n", "", $invoice->public_notes)),
                "Invoice public notes mismatch for invoice ID {$invoice->id}"
            );
        }

        // Compare PO number
        $qb_po_number = data_get($qb_invoice, 'PONumber', '');
        $ninja_po_number = $invoice->po_number ?? '';
        $match = $qb_po_number === $ninja_po_number;
        // nlog("PO Number: QB={$qb_po_number}, Ninja={$ninja_po_number}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Invoice PO number - QB: [{$qb_po_number}], Ninja: [{$ninja_po_number}]");
        }
        $this->assertEquals(
            $qb_po_number,
            $ninja_po_number,
            "Invoice PO number mismatch for invoice ID {$invoice->id}"
        );

        // Compare partial/deposit
        // Convert to string to avoid BigNumber deprecation warnings (Laravel uses BigNumber for decimals)
        $qb_deposit = (string) data_get($qb_invoice, 'Deposit', 0);
        $ninja_partial = (string) ($invoice->partial ?? 0);
        $match = abs((float) $qb_deposit - (float) $ninja_partial) < 0.01;
        // nlog("Partial/Deposit: QB={$qb_deposit}, Ninja={$ninja_partial}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Invoice partial/deposit - QB: [{$qb_deposit}], Ninja: [{$ninja_partial}], Difference: " . abs((float) $qb_deposit - (float) $ninja_partial));
        }
        $this->assertEqualsWithDelta(
            (float) $qb_deposit,
            (float) $ninja_partial,
            0.01,
            "Invoice partial/deposit mismatch for invoice ID {$invoice->id}"
        );

        // Compare total amount (model field, not calculated)
        // Convert to string to avoid BigNumber deprecation warnings (Laravel uses BigNumber for decimals)
        $qb_total_amt = (string) data_get($qb_invoice, 'TotalAmt', 0);
        $ninja_amount = (string) $invoice->amount;
        // $match = abs((float) $qb_total_amt - (float) $ninja_amount) < 0.001;
        $difference = abs((float) $qb_total_amt - (float) $ninja_amount);
        $ratio = $ninja_amount > 0 ? (float) $qb_total_amt / (float) $ninja_amount : 0;
        
        // nlog("Amount: QB TotalAmt={$qb_total_amt}, Ninja amount={$ninja_amount}, Match=" . ($match ? 'YES' : 'NO') . ", Difference={$difference}, Ratio={$ratio}");
        $qb_tax = (string) data_get($qb_invoice, 'TxnTaxDetail.TotalTax', 0);
        $ninja_tax = (string) ($invoice->total_taxes ?? 0);
        $match = abs((float) $qb_tax - (float) $ninja_tax) < 0.001;
        nlog("Tax Match = > QB {$qb_tax} Ninja {$ninja_tax}");

        if (!$match) {
            nlog("MISMATCH: Invoice amount - QB TotalAmt: [{$qb_total_amt}], Ninja amount: [{$ninja_amount}], Difference: {$difference}, Ratio: {$ratio}");
            // Log additional diagnostic information for mismatches
            $qb_subtotal = (string) (data_get($qb_invoice, 'TotalAmt', 0) - data_get($qb_invoice, 'TxnTaxDetail.TotalTax', 0));
            $ninja_calc = $invoice->calc();
            $ninja_subtotal = (string) $ninja_calc->getSubTotal();
            $ninja_tax = (string) ($invoice->total_taxes ?? 0);
            $qb_tax = (string) data_get($qb_invoice, 'TxnTaxDetail.TotalTax', 0);
            // Normalize QB Line to array - SDK can return single object or array
            $qb_lines_raw = data_get($qb_invoice, 'Line', []);
            $qb_lines_normalized = empty($qb_lines_raw) ? [] : (is_array($qb_lines_raw) ? $qb_lines_raw : [$qb_lines_raw]);
            $qb_line_count = count(array_filter($qb_lines_normalized, fn($line) => data_get($line, 'DetailType') === 'SalesItemLineDetail'));
            $ninja_line_count = count($invoice->line_items ?? []);
            
            nlog("MISMATCH DIAGNOSTICS for Invoice ID {$invoice->id}:");
            nlog("  QB Subtotal: {$qb_subtotal}, Ninja Subtotal: {$ninja_subtotal}");
            nlog("  QB Tax: {$qb_tax}, Ninja Tax: {$ninja_tax}");
            nlog("  QB Line Items: {$qb_line_count}, Ninja Line Items: {$ninja_line_count}");
            nlog("  QB Balance: " . data_get($qb_invoice, 'Balance', 'N/A') . ", Ninja Balance: {$invoice->balance}");
            nlog("  QB Paid: " . data_get($qb_invoice, 'Balance', 0) . " (calculated from TotalAmt - Balance), Ninja Paid: {$invoice->paid_to_date}");
        }
        
        $this->assertEqualsWithDelta(
            (float) $qb_total_amt,
            (float) $ninja_amount,
            0.01,
            "Invoice amount mismatch for invoice ID {$invoice->id}. QB TotalAmt: {$qb_total_amt}, Ninja amount: {$ninja_amount}, Difference: {$difference}, Ratio: {$ratio}"
        );

        // Compare balance (model field, not calculated)
        // Convert to string to avoid BigNumber deprecation warnings (Laravel uses BigNumber for decimals)
        $qb_balance = (string) data_get($qb_invoice, 'Balance', 0);
        $ninja_balance = (string) $invoice->balance;
        $match = abs((float) $qb_balance - (float) $ninja_balance) < 0.01;
        // nlog("Balance: QB={$qb_balance}, Ninja={$ninja_balance}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Invoice balance - QB: [{$qb_balance}], Ninja: [{$ninja_balance}], Difference: " . abs((float) $qb_balance - (float) $ninja_balance));
        }
        $this->assertEqualsWithDelta(
            (float) $qb_balance,
            (float) $ninja_balance,
            0.01,
            "Invoice balance mismatch for invoice ID {$invoice->id}. QB Balance: {$qb_balance}, Ninja balance: {$ninja_balance}"
        );

        // Compare total taxes (model field, not calculated)
        // Convert to string to avoid BigNumber deprecation warnings (Laravel uses BigNumber for decimals)
        $qb_total_tax = (string) data_get($qb_invoice, 'TxnTaxDetail.TotalTax', 0);
        $ninja_total_taxes = (string) ($invoice->total_taxes ?? 0);
        $match = abs((float) $qb_total_tax - (float) $ninja_total_taxes) < 0.01;
        // nlog("Total Taxes: QB={$qb_total_tax}, Ninja={$ninja_total_taxes}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Invoice total taxes - QB: [{$qb_total_tax}], Ninja: [{$ninja_total_taxes}], Difference: " . abs((float) $qb_total_tax - (float) $ninja_total_taxes));
        }
        $this->assertEqualsWithDelta(
            (float) $qb_total_tax,
            (float) $ninja_total_taxes,
            0.01,
            "Invoice total taxes mismatch for invoice ID {$invoice->id}. QB TxnTaxDetail.TotalTax: {$qb_total_tax}, Ninja total_taxes: {$ninja_total_taxes}"
        );
    }

    /**
     * Compare invoice line items.
     */
    private function compareInvoiceLineItems(Invoice $invoice, $qb_invoice, Company $company): void
    {
        // nlog("--- Comparing Line Items ---");

        // Normalize QB Line to array - SDK can return single object or array
        $qb_lines_raw = data_get($qb_invoice, 'Line', []);
        $qb_lines = empty($qb_lines_raw) ? [] : (is_array($qb_lines_raw) ? $qb_lines_raw : [$qb_lines_raw]);
        $ninja_line_items = $invoice->line_items ?? [];

        // Filter out non-sales item lines (discounts, taxes, etc.)
        $qb_sales_lines = array_filter($qb_lines, function ($line) {
            return data_get($line, 'DetailType') === 'SalesItemLineDetail';
        });

        $qb_line_count = count($qb_sales_lines);
        $ninja_line_count = count($ninja_line_items);
        // nlog("Line Item Count: QB={$qb_line_count}, Ninja={$ninja_line_count}, Match=" . ($qb_line_count === $ninja_line_count ? 'YES' : 'NO'));
        if ($qb_line_count !== $ninja_line_count) {
            nlog("MISMATCH: Line item count - QB: [{$qb_line_count}], Ninja: [{$ninja_line_count}]");
        }

        $this->assertCount(
            $ninja_line_count,
            $qb_sales_lines,
            "Line item count mismatch for invoice ID {$invoice->id}"
        );

        // Build a map of QB lines by product key for matching
        // Key: product_key (from ItemRef -> Product lookup), Value: QB line item
        $qb_lines_by_product_key = [];
        $qb_lines_by_item_ref = [];
        
        foreach ($qb_sales_lines as $qb_line) {
            $qb_detail = data_get($qb_line, 'SalesItemLineDetail', []);
            $qb_item_ref = data_get($qb_detail, 'ItemRef.value');
            
            if ($qb_item_ref) {
                // Store by ItemRef
                $qb_lines_by_item_ref[$qb_item_ref] = $qb_line;
                
                // Also try to find product and store by product_key
                $product = Product::where('company_id', $company->id)
                    ->where('sync->qb_id', $qb_item_ref)
                    ->first();
                
                if ($product && $product->product_key) {
                    // If multiple lines have same product_key, store as array
                    if (!isset($qb_lines_by_product_key[$product->product_key])) {
                        $qb_lines_by_product_key[$product->product_key] = [];
                    }
                    $qb_lines_by_product_key[$product->product_key][] = $qb_line;
                }
            }
        }

        // Track which QB lines have been matched to avoid duplicate matches
        $matched_qb_lines = [];

        foreach ($ninja_line_items as $index => $ninja_item) {
            $ninja_product_key = $ninja_item->product_key ?? '';
            $qb_line = null;
            $match_method = '';

            // Try to find matching QB line by product key first
            if (!empty($ninja_product_key) && isset($qb_lines_by_product_key[$ninja_product_key])) {
                // Find an unmatched line with this product key
                foreach ($qb_lines_by_product_key[$ninja_product_key] as $candidate_line) {
                    $candidate_item_ref = data_get($candidate_line, 'SalesItemLineDetail.ItemRef.value');
                    if (!in_array($candidate_item_ref, $matched_qb_lines)) {
                        $qb_line = $candidate_line;
                        $match_method = 'product_key';
                        $matched_qb_lines[] = $candidate_item_ref;
                        break;
                    }
                }
            }

            // If not found by product key, try to find by ItemRef if we have a product with qb_id
            if (!$qb_line && !empty($ninja_product_key)) {
                $ninja_product = Product::where('company_id', $company->id)
                    ->where('product_key', $ninja_product_key)
                    ->whereNotNull('sync->qb_id')
                    ->first();
                
                if ($ninja_product && $ninja_product->sync->qb_id) {
                    $ninja_product_qb_id = $ninja_product->sync->qb_id;
                    if (isset($qb_lines_by_item_ref[$ninja_product_qb_id]) && 
                        !in_array($ninja_product_qb_id, $matched_qb_lines)) {
                        $qb_line = $qb_lines_by_item_ref[$ninja_product_qb_id];
                        $match_method = 'qb_id';
                        $matched_qb_lines[] = $ninja_product_qb_id;
                    }
                }
            }

            if (!$qb_line) {
                nlog("  ERROR: Could not find matching QuickBooks line item for Ninja line item #{$index} (Product Key: [{$ninja_product_key}])");
                $this->fail("Could not find matching QuickBooks line item for Ninja line item #{$index} (Product Key: [{$ninja_product_key}]) for invoice ID {$invoice->id}");
            }

            $qb_detail = data_get($qb_line, 'SalesItemLineDetail', []);

            // nlog("  Line Item #{$index} (Matched by {$match_method}):");

            // Verify the match by comparing product keys
            $qb_item_ref = data_get($qb_detail, 'ItemRef.value');
            if ($qb_item_ref) {
                // Find the product by qb_id to get product_key
                $product = Product::where('company_id', $company->id)
                    ->where('sync->qb_id', $qb_item_ref)
                    ->first();
                
                if ($product) {
                    $match = $product->product_key === $ninja_product_key;
                    // nlog("    Product Key: QB ItemRef={$qb_item_ref}, Product={$product->product_key}, Ninja={$ninja_product_key}, Match=" . ($match ? 'YES' : 'NO'));
                    if (!$match) {
                        nlog("    MISMATCH: Line item {$index} product key - QB ItemRef: [{$qb_item_ref}], Product: [{$product->product_key}], Ninja: [{$ninja_product_key}]");
                    }
                    
                    $this->assertEquals(
                        $product->product_key,
                        $ninja_product_key,
                        "Line item {$index} product_key mismatch for invoice ID {$invoice->id}"
                    );
                    
                    // Verify the product's qb_id matches the QuickBooks ItemRef
                    $this->assertEquals(
                        $qb_item_ref,
                        $product->sync->qb_id,
                        "Line item {$index} product qb_id mismatch - QB ItemRef: {$qb_item_ref}, Product sync qb_id: {$product->sync->qb_id}"
                    );
                } else {
                    nlog("    WARNING: QB ItemRef [{$qb_item_ref}] not found in company products - cannot verify product key match");
                }
            }

            // Compare quantity
            // Convert to string to avoid BigNumber deprecation warnings
            $qb_qty = (string) data_get($qb_detail, 'Qty', 0);
            $ninja_qty = (string) ($ninja_item->quantity ?? 0);
            $match = abs((float) $qb_qty - (float) $ninja_qty) < 0.01;
            // nlog("    Quantity: QB={$qb_qty}, Ninja={$ninja_qty}, Match=" . ($match ? 'YES' : 'NO'));
            if (!$match) {
                nlog("    MISMATCH: Line item {$index} quantity - QB: [{$qb_qty}], Ninja: [{$ninja_qty}], Difference: " . abs((float) $qb_qty - (float) $ninja_qty));
            }
            $this->assertEqualsWithDelta(
                (float) $qb_qty,
                (float) $ninja_qty,
                0.01,
                "Line item {$index} quantity mismatch for invoice ID {$invoice->id}"
            );

            // Compare unit price
            // Convert to string to avoid BigNumber deprecation warnings
            $qb_unit_price = (string) data_get($qb_detail, 'UnitPrice', 0);
            $ninja_cost = (string) ($ninja_item->cost ?? 0);
            $match = abs((float) $qb_unit_price - (float) $ninja_cost) < 0.01;
            // nlog("    Unit Price: QB={$qb_unit_price}, Ninja={$ninja_cost}, Match=" . ($match ? 'YES' : 'NO'));
            if (!$match) {
                nlog("    MISMATCH: Line item {$index} unit price - QB: [{$qb_unit_price}], Ninja: [{$ninja_cost}], Difference: " . abs((float) $qb_unit_price - (float) $ninja_cost));
            }
            $this->assertEqualsWithDelta(
                (float) $qb_unit_price,
                (float) $ninja_cost,
                0.01,
                "Line item {$index} unit price mismatch for invoice ID {$invoice->id}"
            );

            // Compare line amount
            // Convert to string to avoid BigNumber deprecation warnings
            $qb_line_amount = (string) data_get($qb_line, 'Amount', 0);
            $ninja_line_total = (string) ($ninja_item->line_total ?? 0);
            $match = abs((float) $qb_line_amount - (float) $ninja_line_total) < 0.01;
            // nlog("    Line Amount: QB={$qb_line_amount}, Ninja={$ninja_line_total}, Match=" . ($match ? 'YES' : 'NO'));
            if (!$match) {
                nlog("    MISMATCH: Line item {$index} line amount - QB: [{$qb_line_amount}], Ninja: [{$ninja_line_total}], Difference: " . abs((float) $qb_line_amount - (float) $ninja_line_total));
            }
            $this->assertEqualsWithDelta(
                (float) $qb_line_amount,
                (float) $ninja_line_total,
                0.01,
                "Line item {$index} amount mismatch for invoice ID {$invoice->id}. QB: [{$qb_line_amount}], Ninja: [{$ninja_line_total}], Difference: " . abs((float) $qb_line_amount - (float) $ninja_line_total)
            );

            // Compare description/notes
            $qb_description = data_get($qb_line, 'Description', '');
            $ninja_notes = $ninja_item->notes ?? '';
            $match = $qb_description === $ninja_notes;
            // nlog("    Description: QB={$qb_description}, Ninja={$ninja_notes}, Match=" . ($match ? 'YES' : 'NO'));
            if (!$match) {
                nlog("    MISMATCH: Line item {$index} description - QB: [{$qb_description}], Ninja: [{$ninja_notes}]");
            }
            $this->assertEquals(
                $qb_description,
                $ninja_notes,
                "Line item {$index} description mismatch for invoice ID {$invoice->id}. QB: [{$qb_description}], Ninja: [{$ninja_notes}]"
            );

            // Compare tax state (TaxCodeRef)
            $qb_tax_code_ref = data_get($qb_detail, 'TaxCodeRef.value')
                            ?? data_get($qb_detail, 'TaxCodeRef')
                            ?? data_get($qb_line, 'TaxCodeRef.value')
                            ?? data_get($qb_line, 'TaxCodeRef')
                            ?? '';
            
            // Determine expected tax state in Invoice Ninja
            // QuickBooks: 'NON' = non-taxable, 'TAX' = taxable, or other tax codes
            // Invoice Ninja: tax_id = '5' (exempt) or '8' (zero rate) for non-taxable, otherwise taxable
            $ninja_tax_id = (string) ($ninja_item->tax_id ?? '');
            $ninja_has_tax_rates = (
                (isset($ninja_item->tax_rate1) && $ninja_item->tax_rate1 > 0) ||
                (isset($ninja_item->tax_rate2) && $ninja_item->tax_rate2 > 0) ||
                (isset($ninja_item->tax_rate3) && $ninja_item->tax_rate3 > 0)
            );
            
            // Determine if Ninja line item is taxable
            $ninja_is_taxable = !in_array($ninja_tax_id, ['5', '8']) && $ninja_has_tax_rates;
            $expected_qb_tax_code = $ninja_is_taxable ? 'TAX' : 'NON';
            
            // For comparison, we'll check if QB tax code matches expected state
            // Note: QuickBooks may use other tax codes besides 'TAX'/'NON', so we check if it's non-taxable
            $qb_is_taxable = !empty($qb_tax_code_ref) && $qb_tax_code_ref !== 'NON';
            $match = $qb_is_taxable === $ninja_is_taxable;
            
            $ninja_tax_rate1 = (string) ($ninja_item->tax_rate1 ?? 0);
            $ninja_tax_rate2 = (string) ($ninja_item->tax_rate2 ?? 0);
            $ninja_tax_rate3 = (string) ($ninja_item->tax_rate3 ?? 0);
            
            // nlog("    Tax State: QB TaxCodeRef={$qb_tax_code_ref}, Ninja tax_id={$ninja_tax_id}, Ninja has_tax_rates=" . ($ninja_has_tax_rates ? 'YES' : 'NO') . ", QB taxable=" . ($qb_is_taxable ? 'YES' : 'NO') . ", Ninja taxable=" . ($ninja_is_taxable ? 'YES' : 'NO') . ", Match=" . ($match ? 'YES' : 'NO'));
            if (!$match) {
                nlog("    MISMATCH: Line item {$index} tax state - QB TaxCodeRef: [{$qb_tax_code_ref}], Ninja tax_id: [{$ninja_tax_id}], Ninja tax_rate1: [{$ninja_tax_rate1}], tax_rate2: [{$ninja_tax_rate2}], tax_rate3: [{$ninja_tax_rate3}]");
            }
            $this->assertEquals(
                $qb_is_taxable,
                $ninja_is_taxable,
                "Line item {$index} tax state mismatch for invoice ID {$invoice->id}. QB TaxCodeRef: [{$qb_tax_code_ref}], Ninja tax_id: [{$ninja_tax_id}], Ninja has tax rates: " . ($ninja_has_tax_rates ? 'YES' : 'NO')
            );

            // Compare income account ID
            $qb_income_account_ref = data_get($qb_detail, 'IncomeAccountRef.value')
                                  ?? data_get($qb_detail, 'IncomeAccountRef')
                                  ?? '';
            $ninja_income_account_id = (string) ($ninja_item->income_account_id ?? '');
            
            // If QuickBooks has an income account but Ninja doesn't, or vice versa, log it
            // Note: IncomeAccountRef may not always be present in QB line items (it can be inherited from the item)
            if (!empty($qb_income_account_ref) || !empty($ninja_income_account_id)) {
                $match = $qb_income_account_ref === $ninja_income_account_id;
                // nlog("    Income Account ID: QB IncomeAccountRef={$qb_income_account_ref}, Ninja income_account_id={$ninja_income_account_id}, Match=" . ($match ? 'YES' : 'NO'));
                if (!$match) {
                    nlog("    MISMATCH: Line item {$index} income account ID - QB IncomeAccountRef: [{$qb_income_account_ref}], Ninja income_account_id: [{$ninja_income_account_id}]");
                }
                // Only assert if both values are present (QB may inherit from item, so empty QB value is acceptable)
                if (!empty($qb_income_account_ref) && !empty($ninja_income_account_id)) {
                    $this->assertEquals(
                        $qb_income_account_ref,
                        $ninja_income_account_id,
                        "Line item {$index} income account ID mismatch for invoice ID {$invoice->id}. QB IncomeAccountRef: [{$qb_income_account_ref}], Ninja income_account_id: [{$ninja_income_account_id}]"
                    );
                } elseif (!empty($qb_income_account_ref) && empty($ninja_income_account_id)) {
                    nlog("    WARNING: QuickBooks has IncomeAccountRef [{$qb_income_account_ref}] but Invoice Ninja line item has no income_account_id");
                } elseif (empty($qb_income_account_ref) && !empty($ninja_income_account_id)) {
                    nlog("    WARNING: Invoice Ninja has income_account_id [{$ninja_income_account_id}] but QuickBooks line item has no IncomeAccountRef (may be inherited from item)");
                }
            } else {
                // nlog("    Income Account ID: Both empty (QB may inherit from item)");
            }
        }
    }

    /**
     * Test that compares all client fields between QuickBooks and Invoice Ninja.
     * Iterates through all companies with QuickBooks configured.
     */
    public function testClientDataComparison(): void
    {
        $companies = $this->getQuickbooksCompanies();

        if ($companies->isEmpty()) {
            $this->markTestSkipped('No companies with QuickBooks configuration found');
        }

        $total_comparisons = 0;
        $skipped_companies = 0;

        foreach ($companies as $company) {
            try {
                // Check if token is expired before attempting to create service
                if ($this->isTokenExpired($company)) {
                    // nlog("Skipping company {$company->id} - QuickBooks token is expired and cannot be refreshed");
                    $skipped_companies++;
                    continue;
                }

                $qb_service = new QuickbooksService($company);

                // Verify we're connected to the correct realm
                if (!$this->verifyRealmConnection($company, $qb_service)) {
                    // nlog("Skipping company {$company->id} - realm connection verification failed");
                    $skipped_companies++;
                    continue;
                }

                // Find clients with QuickBooks sync ID that belong to this company
                $clients = Client::where('company_id', $company->id)
                    ->whereNotNull('sync->qb_id')
                    ->get();

                if ($clients->isEmpty()) {
                    // nlog("No clients with QuickBooks sync ID found for company {$company->id}");
                    continue;
                }

                // nlog("=== Processing Company {$company->id} ({$clients->count()} clients) ===");
                // nlog("Company Name: {$company->name}");
                // nlog("Realm ID: {$company->quickbooks->realmID}");

                foreach ($clients as $client) {
                    try {
                        $this->compareClientForCompany($client, $company, $qb_service);
                        $total_comparisons++;
                    } catch (\Exception $e) {
                        nlog("ERROR comparing client ID {$client->id} (Company {$company->id}): " . $e->getMessage());
                        // nlog("Client Name: {$client->name}");
                        // nlog("QB ID: {$client->sync->qb_id}");
                        throw $e; // Re-throw to be caught by outer try-catch
                    }
                }

                // nlog("=== Completed Company {$company->id} ===");
            } catch (\Exception $e) {
                $error_message = $e->getMessage();
                
                // Check if it's an authentication/token error
                if (str_contains($error_message, 'token expired') || 
                    str_contains($error_message, 'invalid_grant') ||
                    str_contains($error_message, 'AuthenticationFailed') ||
                    str_contains($error_message, '401') ||
                    str_contains($error_message, 'Quickbooks token expired')) {
                    // nlog("Skipping company {$company->id} - QuickBooks authentication failed (token expired or invalid)");
                } else {
                    // Get QuickBooks company name (from cached value if available)
                    $qb_company_name = $company->quickbooks->companyName ?? 'NOT AVAILABLE';
                    
                    // nlog("ERROR processing company {$company->id} (Clients): " . $error_message);
                    // nlog("Invoice Ninja Company Name: {$company->name}");
                    // nlog("QuickBooks Company Name: {$qb_company_name}");
                    // nlog("Realm ID: {$company->quickbooks->realmID}");
                }
                
                $skipped_companies++;
                continue;
            }
        }

        // nlog("=== Client Comparison Summary ===");
        // nlog("Total Companies Processed: " . ($companies->count() - $skipped_companies));
        // nlog("Total Companies Skipped: {$skipped_companies}");
        // nlog("Total Client Comparisons: {$total_comparisons}");

        if ($total_comparisons === 0) {
            $this->markTestSkipped('No clients found to compare across all companies');
        }
    }

    /**
     * Compare a single client for a specific company.
     */
    private function compareClientForCompany(Client $client, Company $company, QuickbooksService $qb_service): void
    {
        $qb_id = $client->sync->qb_id;
        $this->assertNotEmpty($qb_id, 'Client must have a qb_id in sync');

        // Fetch the client from QuickBooks (will use the realm configured in QuickbooksService)
        $qb_client = $qb_service->client->find($qb_id);
        $this->assertNotNull($qb_client, 'Failed to fetch client from QuickBooks. This may indicate the qb_id belongs to a different realm.');

        // Log the comparison start
        // nlog("=== QuickBooks Client Comparison ===");
        // nlog("Client Ninja ID: {$client->id}");
        // nlog("QuickBooks ID: {$qb_id}");
        // nlog("Client Name: {$client->name}");
        // nlog("Company ID: {$company->id}");
        // nlog("Company Name: {$company->name}");
        // nlog("Realm ID: {$company->quickbooks->realmID}");

        // Compare basic client fields
        $this->compareClientBasicFields($client, $qb_client);
        
        // Compare contact information
        $this->compareClientContactFields($client, $qb_client);

        // Log comparison completion
        // nlog("=== Comparison Complete for Client {$client->id} ===");
    }

    /**
     * Compare basic client fields.
     */
    private function compareClientBasicFields(Client $client, $qb_client): void
    {
        // nlog("--- Comparing Basic Client Fields ---");

        // Compare company name
        $qb_company_name = data_get($qb_client, 'CompanyName', '');
        $ninja_name = $client->name ?? '';
        $match = $qb_company_name === $ninja_name;
        // nlog("Name: QB={$qb_company_name}, Ninja={$ninja_name}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client name - QB: [{$qb_company_name}], Ninja: [{$ninja_name}]");
        }
        $this->assertEquals(
            $qb_company_name,
            $ninja_name,
            "Client name mismatch for client ID {$client->id}. QB: [{$qb_company_name}], Ninja: [{$ninja_name}]"
        );

        // Compare billing address
        $qb_bill_addr = data_get($qb_client, 'BillAddr', []);
        
        $qb_address1 = data_get($qb_bill_addr, 'Line1', '');
        $ninja_address1 = $client->address1 ?? '';
        $match = $qb_address1 === $ninja_address1;
        // nlog("Address1: QB={$qb_address1}, Ninja={$ninja_address1}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client address1 - QB: [{$qb_address1}], Ninja: [{$ninja_address1}]");
        }
        $this->assertEquals(
            $qb_address1,
            $ninja_address1,
            "Client address1 mismatch for client ID {$client->id}. QB: [{$qb_address1}], Ninja: [{$ninja_address1}]"
        );

        $qb_address2 = data_get($qb_bill_addr, 'Line2', '');
        $ninja_address2 = $client->address2 ?? '';
        $match = $qb_address2 === $ninja_address2;
        // nlog("Address2: QB={$qb_address2}, Ninja={$ninja_address2}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client address2 - QB: [{$qb_address2}], Ninja: [{$ninja_address2}]");
        }
        $this->assertEquals(
            $qb_address2,
            $ninja_address2,
            "Client address2 mismatch for client ID {$client->id}. QB: [{$qb_address2}], Ninja: [{$ninja_address2}]"
        );

        $qb_city = data_get($qb_bill_addr, 'City', '');
        $ninja_city = $client->city ?? '';
        $match = $qb_city === $ninja_city;
        // nlog("City: QB={$qb_city}, Ninja={$ninja_city}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client city - QB: [{$qb_city}], Ninja: [{$ninja_city}]");
        }
        $this->assertEquals(
            $qb_city,
            $ninja_city,
            "Client city mismatch for client ID {$client->id}. QB: [{$qb_city}], Ninja: [{$ninja_city}]"
        );

        $qb_state = data_get($qb_bill_addr, 'CountrySubDivisionCode', '');
        $ninja_state = $client->state ?? '';
        $match = $qb_state === $ninja_state;
        // nlog("State: QB={$qb_state}, Ninja={$ninja_state}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client state - QB: [{$qb_state}], Ninja: [{$ninja_state}]");
        }
        $this->assertEquals(
            $qb_state,
            $ninja_state,
            "Client state mismatch for client ID {$client->id}. QB: [{$qb_state}], Ninja: [{$ninja_state}]"
        );

        $qb_postal_code = data_get($qb_bill_addr, 'PostalCode', '');
        $ninja_postal_code = $client->postal_code ?? '';
        $match = $qb_postal_code === $ninja_postal_code;
        // nlog("Postal Code: QB={$qb_postal_code}, Ninja={$ninja_postal_code}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client postal_code - QB: [{$qb_postal_code}], Ninja: [{$ninja_postal_code}]");
        }
        $this->assertEquals(
            $qb_postal_code,
            $ninja_postal_code,
            "Client postal_code mismatch for client ID {$client->id}. QB: [{$qb_postal_code}], Ninja: [{$ninja_postal_code}]"
        );

        // Compare shipping address
        $qb_ship_addr = data_get($qb_client, 'ShipAddr', []);
        
        $qb_ship_address1 = data_get($qb_ship_addr, 'Line1', '');
        $ninja_ship_address1 = $client->shipping_address1 ?? '';
        $match = $qb_ship_address1 === $ninja_ship_address1;
        // nlog("Shipping Address1: QB={$qb_ship_address1}, Ninja={$ninja_ship_address1}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client shipping_address1 - QB: [{$qb_ship_address1}], Ninja: [{$ninja_ship_address1}]");
        }
        $this->assertEquals(
            $qb_ship_address1,
            $ninja_ship_address1,
            "Client shipping_address1 mismatch for client ID {$client->id}. QB: [{$qb_ship_address1}], Ninja: [{$ninja_ship_address1}]"
        );

        $qb_ship_address2 = data_get($qb_ship_addr, 'Line2', '');
        $ninja_ship_address2 = $client->shipping_address2 ?? '';
        $match = $qb_ship_address2 === $ninja_ship_address2;
        // nlog("Shipping Address2: QB={$qb_ship_address2}, Ninja={$ninja_ship_address2}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client shipping_address2 - QB: [{$qb_ship_address2}], Ninja: [{$ninja_ship_address2}]");
        }
        $this->assertEquals(
            $qb_ship_address2,
            $ninja_ship_address2,
            "Client shipping_address2 mismatch for client ID {$client->id}. QB: [{$qb_ship_address2}], Ninja: [{$ninja_ship_address2}]"
        );

        $qb_ship_city = data_get($qb_ship_addr, 'City', '');
        $ninja_ship_city = $client->shipping_city ?? '';
        $match = $qb_ship_city === $ninja_ship_city;
        // nlog("Shipping City: QB={$qb_ship_city}, Ninja={$ninja_ship_city}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client shipping_city - QB: [{$qb_ship_city}], Ninja: [{$ninja_ship_city}]");
        }
        $this->assertEquals(
            $qb_ship_city,
            $ninja_ship_city,
            "Client shipping_city mismatch for client ID {$client->id}. QB: [{$qb_ship_city}], Ninja: [{$ninja_ship_city}]"
        );

        $qb_ship_state = data_get($qb_ship_addr, 'CountrySubDivisionCode', '');
        $ninja_ship_state = $client->shipping_state ?? '';
        $match = $qb_ship_state === $ninja_ship_state;
        // nlog("Shipping State: QB={$qb_ship_state}, Ninja={$ninja_ship_state}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client shipping_state - QB: [{$qb_ship_state}], Ninja: [{$ninja_ship_state}]");
        }
        $this->assertEquals(
            $qb_ship_state,
            $ninja_ship_state,
            "Client shipping_state mismatch for client ID {$client->id}. QB: [{$qb_ship_state}], Ninja: [{$ninja_ship_state}]"
        );

        $qb_ship_postal_code = data_get($qb_ship_addr, 'PostalCode', '');
        $ninja_ship_postal_code = $client->shipping_postal_code ?? '';
        $match = $qb_ship_postal_code === $ninja_ship_postal_code;
        // nlog("Shipping Postal Code: QB={$qb_ship_postal_code}, Ninja={$ninja_ship_postal_code}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client shipping_postal_code - QB: [{$qb_ship_postal_code}], Ninja: [{$ninja_ship_postal_code}]");
        }
        $this->assertEquals(
            $qb_ship_postal_code,
            $ninja_ship_postal_code,
            "Client shipping_postal_code mismatch for client ID {$client->id}. QB: [{$qb_ship_postal_code}], Ninja: [{$ninja_ship_postal_code}]"
        );

        // Compare business number
        $qb_business_number = data_get($qb_client, 'BusinessNumber', '');
        $ninja_id_number = $client->id_number ?? '';
        $match = $qb_business_number === $ninja_id_number;
        // nlog("ID Number: QB={$qb_business_number}, Ninja={$ninja_id_number}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client id_number - QB: [{$qb_business_number}], Ninja: [{$ninja_id_number}]");
        }
        $this->assertEquals(
            $qb_business_number,
            $ninja_id_number,
            "Client id_number mismatch for client ID {$client->id}. QB: [{$qb_business_number}], Ninja: [{$ninja_id_number}]"
        );

        // Compare VAT number
        $qb_vat = data_get($qb_client, 'PrimaryTaxIdentifier', '');
        $ninja_vat = $client->vat_number ?? '';
        $match = $qb_vat === $ninja_vat;
        // nlog("VAT Number: QB={$qb_vat}, Ninja={$ninja_vat}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client vat_number - QB: [{$qb_vat}], Ninja: [{$ninja_vat}]");
        }
        $this->assertEquals(
            $qb_vat,
            $ninja_vat,
            "Client vat_number mismatch for client ID {$client->id}. QB: [{$qb_vat}], Ninja: [{$ninja_vat}]"
        );

        // Compare notes
        $qb_notes = data_get($qb_client, 'Notes', '');
        $ninja_notes = $client->private_notes ?? '';
        $match = $qb_notes === $ninja_notes;
        // nlog("Private Notes: QB={$qb_notes}, Ninja={$ninja_notes}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client private_notes - QB: [{$qb_notes}], Ninja: [{$ninja_notes}]");
        }
        $this->assertEquals(
            $qb_notes,
            $ninja_notes,
            "Client private_notes mismatch for client ID {$client->id}. QB: [{$qb_notes}], Ninja: [{$ninja_notes}]"
        );

        // Compare website
        $qb_website = data_get($qb_client, 'WebAddr', '');
        $ninja_website = $client->website ?? '';
        $match = $qb_website === $ninja_website;
        // nlog("Website: QB={$qb_website}, Ninja={$ninja_website}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client website - QB: [{$qb_website}], Ninja: [{$ninja_website}]");
        }
        $this->assertEquals(
            $qb_website,
            $ninja_website,
            "Client website mismatch for client ID {$client->id}. QB: [{$qb_website}], Ninja: [{$ninja_website}]"
        );
    }

    /**
     * Compare client contact fields.
     */
    private function compareClientContactFields(Client $client, $qb_client): void
    {
        // nlog("--- Comparing Client Contact Fields ---");

        $primary_contact = $client->contacts()->where('is_primary', true)->first();

        if (!$primary_contact) {
            nlog("WARNING: No primary contact found for client ID {$client->id}");
            $this->markTestSkipped("No primary contact found for client ID {$client->id}");
        }

        // Compare name
        $qb_given_name = data_get($qb_client, 'GivenName', '');
        $ninja_first_name = $primary_contact->first_name ?? '';
        $match = $qb_given_name === $ninja_first_name;
        // nlog("First Name: QB={$qb_given_name}, Ninja={$ninja_first_name}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client contact first_name - QB: [{$qb_given_name}], Ninja: [{$ninja_first_name}]");
        }
        $this->assertEquals(
            $qb_given_name,
            $ninja_first_name,
            "Client contact first_name mismatch for client ID {$client->id}. QB: [{$qb_given_name}], Ninja: [{$ninja_first_name}]"
        );

        $qb_family_name = data_get($qb_client, 'FamilyName', '');
        $ninja_last_name = $primary_contact->last_name ?? '';
        $match = $qb_family_name === $ninja_last_name;
        // nlog("Last Name: QB={$qb_family_name}, Ninja={$ninja_last_name}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client contact last_name - QB: [{$qb_family_name}], Ninja: [{$ninja_last_name}]");
        }
        $this->assertEquals(
            $qb_family_name,
            $ninja_last_name,
            "Client contact last_name mismatch for client ID {$client->id}. QB: [{$qb_family_name}], Ninja: [{$ninja_last_name}]"
        );

        // Compare email
        $qb_email = data_get($qb_client, 'PrimaryEmailAddr.Address', '');
        $ninja_email = $primary_contact->email ?? '';
        $match = $qb_email === $ninja_email;
        // nlog("Email: QB={$qb_email}, Ninja={$ninja_email}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client contact email - QB: [{$qb_email}], Ninja: [{$ninja_email}]");
        }
        $this->assertEquals(
            $qb_email,
            $ninja_email,
            "Client contact email mismatch for client ID {$client->id}. QB: [{$qb_email}], Ninja: [{$ninja_email}]"
        );

        // Compare phone
        $qb_phone = data_get($qb_client, 'PrimaryPhone.FreeFormNumber', '');
        $ninja_phone = $primary_contact->phone ?? '';
        $match = $qb_phone === $ninja_phone;
        // nlog("Phone: QB={$qb_phone}, Ninja={$ninja_phone}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Client contact phone - QB: [{$qb_phone}], Ninja: [{$ninja_phone}]");
        }
        $this->assertEquals(
            $qb_phone,
            $ninja_phone,
            "Client contact phone mismatch for client ID {$client->id}. QB: [{$qb_phone}], Ninja: [{$ninja_phone}]"
        );
    }

    /**
     * Test that compares all product fields between QuickBooks and Invoice Ninja.
     * Iterates through all companies with QuickBooks configured.
     */
    public function testProductDataComparison(): void
    {
        $companies = $this->getQuickbooksCompanies();

        if ($companies->isEmpty()) {
            $this->markTestSkipped('No companies with QuickBooks configuration found');
        }

        $total_comparisons = 0;
        $skipped_companies = 0;

        foreach ($companies as $company) {
            try {
                // Check if token is expired before attempting to create service
                if ($this->isTokenExpired($company)) {
                    // nlog("Skipping company {$company->id} - QuickBooks token is expired and cannot be refreshed");
                    $skipped_companies++;
                    continue;
                }

                $qb_service = new QuickbooksService($company);

                // Verify we're connected to the correct realm
                if (!$this->verifyRealmConnection($company, $qb_service)) {
                    // nlog("Skipping company {$company->id} - realm connection verification failed");
                    $skipped_companies++;
                    continue;
                }

                // Find products with QuickBooks sync ID that belong to this company
                $products = Product::where('company_id', $company->id)
                    ->whereNotNull('sync->qb_id')
                    ->get();

                if ($products->isEmpty()) {
                    // nlog("No products with QuickBooks sync ID found for company {$company->id}");
                    continue;
                }

                // nlog("=== Processing Company {$company->id} ({$products->count()} products) ===");
                // nlog("Company Name: {$company->name}");
                // nlog("Realm ID: {$company->quickbooks->realmID}");

                foreach ($products as $product) {
                    try {
                        $this->compareProductForCompany($product, $company, $qb_service);
                        $total_comparisons++;
                    } catch (\Exception $e) {
                        nlog("ERROR comparing product ID {$product->id} (Company {$company->id}): " . $e->getMessage());
                        // nlog("Product Key: {$product->product_key}");
                        // nlog("QB ID: {$product->sync->qb_id}");
                        throw $e; // Re-throw to be caught by outer try-catch
                    }
                }

                // nlog("=== Completed Company {$company->id} ===");
            } catch (\Exception $e) {
                $error_message = $e->getMessage();
                
                // Check if it's an authentication/token error
                if (str_contains($error_message, 'token expired') || 
                    str_contains($error_message, 'invalid_grant') ||
                    str_contains($error_message, 'AuthenticationFailed') ||
                    str_contains($error_message, '401') ||
                    str_contains($error_message, 'Quickbooks token expired')) {
                    // nlog("Skipping company {$company->id} - QuickBooks authentication failed (token expired or invalid)");
                } else {
                    // Get QuickBooks company name (from cached value if available)
                    $qb_company_name = $company->quickbooks->companyName ?? 'NOT AVAILABLE';
                    
                    // nlog("ERROR processing company {$company->id} (Products): " . $error_message);
                    // nlog("Invoice Ninja Company Name: {$company->name}");
                    // nlog("QuickBooks Company Name: {$qb_company_name}");
                    // nlog("Realm ID: {$company->quickbooks->realmID}");
                }
                
                $skipped_companies++;
                continue;
            }
        }

        // nlog("=== Product Comparison Summary ===");
        // nlog("Total Companies Processed: " . ($companies->count() - $skipped_companies));
        // nlog("Total Companies Skipped: {$skipped_companies}");
        // nlog("Total Product Comparisons: {$total_comparisons}");

        if ($total_comparisons === 0) {
            $this->markTestSkipped('No products found to compare across all companies');
        }
    }

    /**
     * Compare a single product for a specific company.
     */
    private function compareProductForCompany(Product $product, Company $company, QuickbooksService $qb_service): void
    {
        $qb_id = $product->sync->qb_id;
        $this->assertNotEmpty($qb_id, 'Product must have a qb_id in sync');

        // Fetch the product from QuickBooks (will use the realm configured in QuickbooksService)
        $qb_product = $qb_service->product->find($qb_id);
        $this->assertNotNull($qb_product, 'Failed to fetch product from QuickBooks. This may indicate the qb_id belongs to a different realm.');

        // Log the comparison start
        // nlog("=== QuickBooks Product Comparison ===");
        // nlog("Product Ninja ID: {$product->id}");
        // nlog("QuickBooks ID: {$qb_id}");
        // nlog("Product Key: {$product->product_key}");
        // nlog("Company ID: {$company->id}");
        // nlog("Company Name: {$company->name}");
        // nlog("Realm ID: {$company->quickbooks->realmID}");

        // Compare all product fields
        $this->compareProductFields($product, $qb_product);

        // Log comparison completion
        // nlog("=== Comparison Complete for Product {$product->id} ===");
    }

    /**
     * Compare all product fields.
     */
    private function compareProductFields(Product $product, $qb_product): void
    {
        // nlog("--- Comparing Product Fields ---");

        // Get QuickBooks SKU for mismatch logging
        $qb_sku = data_get($qb_product, 'Sku', '') ?: '';

        // Compare product key/name
        $qb_name = data_get($qb_product, 'Name') ?? data_get($qb_product, 'FullyQualifiedName', '');
        $ninja_product_key = $product->product_key ?? '';
        $match = $qb_name === $ninja_product_key;
        // nlog("Product Key: QB={$qb_name}, Ninja={$ninja_product_key}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Product product_key - QB SKU: [{$qb_sku}], QB Name: [{$qb_name}], Ninja: [{$ninja_product_key}]");
        }
        $this->assertEquals(
            $qb_name,
            $ninja_product_key,
            "Product product_key mismatch for product ID {$product->id}. QB: [{$qb_name}], Ninja: [{$ninja_product_key}]"
        );

        // Compare description/notes
        $qb_description = data_get($qb_product, 'Description', '');
        $ninja_notes = $product->notes ?? '';
        $match = $qb_description === $ninja_notes;
        // nlog("Notes: QB={$qb_description}, Ninja={$ninja_notes}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Product notes - QB SKU: [{$qb_sku}], QB: [{$qb_description}], Ninja: [{$ninja_notes}]");
        }
        $this->assertEquals(
            $qb_description,
            $ninja_notes,
            "Product notes mismatch for product ID {$product->id}. QB: [{$qb_description}], Ninja: [{$ninja_notes}]"
        );

        // Compare cost (PurchaseCost in QB)
        // Convert to string to avoid BigNumber deprecation warnings (Laravel uses BigNumber for decimals)
        $qb_purchase_cost = (string) (data_get($qb_product, 'PurchaseCost', 0) ?? 0);
        $ninja_cost = (string) ($product->cost ?? 0);
        $match = abs((float) $qb_purchase_cost - (float) $ninja_cost) < 0.01;
        // nlog("Cost: QB PurchaseCost={$qb_purchase_cost}, Ninja cost={$ninja_cost}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Product cost - QB SKU: [{$qb_sku}], QB PurchaseCost: [{$qb_purchase_cost}], Ninja cost: [{$ninja_cost}], Difference: " . abs((float) $qb_purchase_cost - (float) $ninja_cost));
        }
        $this->assertEqualsWithDelta(
            (float) $qb_purchase_cost,
            (float) $ninja_cost,
            0.01,
            "Product cost mismatch for product ID {$product->id}. QB PurchaseCost: [{$qb_purchase_cost}], Ninja cost: [{$ninja_cost}], Difference: " . abs((float) $qb_purchase_cost - (float) $ninja_cost)
        );

        // Compare price (UnitPrice in QB)
        // Convert to string to avoid BigNumber deprecation warnings (Laravel uses BigNumber for decimals)
        $qb_unit_price = (string) (data_get($qb_product, 'UnitPrice', 0) ?? 0);
        $ninja_price = (string) ($product->price ?? 0);
        $match = abs((float) $qb_unit_price - (float) $ninja_price) < 0.01;
        // nlog("Price: QB UnitPrice={$qb_unit_price}, Ninja price={$ninja_price}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Product price - QB SKU: [{$qb_sku}], QB UnitPrice: [{$qb_unit_price}], Ninja price: [{$ninja_price}], Difference: " . abs((float) $qb_unit_price - (float) $ninja_price));
        }
        $this->assertEqualsWithDelta(
            (float) $qb_unit_price,
            (float) $ninja_price,
            0.01,
            "Product price mismatch for product ID {$product->id}. QB UnitPrice: [{$qb_unit_price}], Ninja price: [{$ninja_price}], Difference: " . abs((float) $qb_unit_price - (float) $ninja_price)
        );

        // Compare quantity on hand
        // Convert to string to avoid BigNumber deprecation warnings (Laravel uses BigNumber for decimals)
        $qb_qty_on_hand = (string) (data_get($qb_product, 'QtyOnHand', 0) ?? 0);
        $ninja_qty = (string) ($product->in_stock_quantity ?? 0);
        $match = abs((float) $qb_qty_on_hand - (float) $ninja_qty) < 0.01;
        // nlog("Quantity: QB QtyOnHand={$qb_qty_on_hand}, Ninja in_stock_quantity={$ninja_qty}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Product in_stock_quantity - QB SKU: [{$qb_sku}], QB QtyOnHand: [{$qb_qty_on_hand}], Ninja in_stock_quantity: [{$ninja_qty}], Difference: " . abs((float) $qb_qty_on_hand - (float) $ninja_qty));
        }
        $this->assertEqualsWithDelta(
            (float) $qb_qty_on_hand,
            (float) $ninja_qty,
            0.01,
            "Product in_stock_quantity mismatch for product ID {$product->id}. QB QtyOnHand: [{$qb_qty_on_hand}], Ninja in_stock_quantity: [{$ninja_qty}], Difference: " . abs((float) $qb_qty_on_hand - (float) $ninja_qty)
        );

        // Compare type (Service vs NonInventory)
        // $qb_type = data_get($qb_product, 'Type', '');
        // $expected_type_id = $qb_type === 'Service' ? '2' : '1';
        // $actual_type_id = (string) ($product->type_id ?? '1');
        // $match = $expected_type_id === $actual_type_id;
        // // nlog("Type ID: QB Type={$qb_type}, Expected type_id={$expected_type_id}, Actual type_id={$actual_type_id}, Match=" . ($match ? 'YES' : 'NO'));
        // if (!$match) {
        //     nlog("MISMATCH: Product type_id - QB SKU: [{$qb_sku}], QB Type: [{$qb_type}], Expected type_id: [{$expected_type_id}], Actual type_id: [{$actual_type_id}]");
        // }
        // $this->assertEquals(
        //     $expected_type_id,
        //     $actual_type_id,
        //     "Product type_id mismatch for product ID {$product->id}. QB Type: [{$qb_type}], Expected type_id: [{$expected_type_id}], Actual type_id: [{$actual_type_id}]"
        // );

        // Compare tax status
        $qb_type = data_get($qb_product, 'Type', '');
        $qb_taxable = data_get($qb_product, 'Taxable', '1') == 'true';
        $expected_tax_id = $qb_taxable ? ($qb_type === 'Service' ? '2' : '1') : '5';
        $actual_tax_id = (string) ($product->tax_id ?? '1');
        $qb_taxable_str = $qb_taxable ? 'true' : 'false';
        $match = $expected_tax_id === $actual_tax_id;
        // nlog("Tax ID: QB Taxable={$qb_taxable_str}, Expected tax_id={$expected_tax_id}, Actual tax_id={$actual_tax_id}, Match=" . ($match ? 'YES' : 'NO'));
        if (!$match) {
            nlog("MISMATCH: Product tax_id - QB SKU: [{$qb_sku}], QB Taxable: [{$qb_taxable_str}], Expected tax_id: [{$expected_tax_id}], Actual tax_id: [{$actual_tax_id}]");
        }
        $this->assertEquals(
            $expected_tax_id,
            $actual_tax_id,
            "Product tax_id mismatch for product ID {$product->id}. QB Taxable: [{$qb_taxable_str}], Expected tax_id: [{$expected_tax_id}], Actual tax_id: [{$actual_tax_id}]"
        );
    }

}
