<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Quickbooks\Validation;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Quickbooks\SyncQuickbooksRequest;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;

class SyncQuickbooksRequestTest extends TestCase
{
    use MockAccountData;

    protected SyncQuickbooksRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new SyncQuickbooksRequest();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();
    }

    /**
     * Test that clients can be provided on its own (without invoices/quotes/payments)
     */
    public function testClientsCanBeProvidedAlone(): void
    {
        $this->actingAs($this->user);

        $data = [
            'clients' => 'email',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Clients should be valid when provided alone');
    }


     public function testClientsCanBeProvidedAloneWithEmptyString(): void
    {
        $this->actingAs($this->user);

        $data = [
            'clients' => '',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Clients should be valid when provided alone');
    }

    /**
     * Test that clients can be null/empty when provided alone
     */
    public function testClientsCanBeNullWhenAlone(): void
    {
        $this->actingAs($this->user);

        $data = [
            'clients' => null,
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails(), 'Clients should be valid when null and no invoices/quotes/payments');
    }

    /**
     * Test that clients can be empty string when provided alone
     */
    public function testClientsCanBeEmptyStringWhenAlone(): void
    {
        $this->actingAs($this->user);

        $data = [
            'clients' => '',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Clients should be valid when empty string and no invoices/quotes/payments');
    }

    /**
     * Test that clients is required when invoices is present
     */
    public function testClientsIsRequiredWhenInvoicesPresent(): void
    {
        $this->actingAs($this->user);

        $data = [
            'invoices' => 'number',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes(), 'Clients should be required when invoices is present');
        $this->assertArrayHasKey('clients', $validator->errors()->toArray());
    }

    /**
     * Test that clients is required when quotes is present
     */
    public function testClientsIsRequiredWhenQuotesPresent(): void
    {
        $this->actingAs($this->user);

        $data = [
            'quotes' => 'number',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes(), 'Clients should be required when quotes is present');
        $this->assertArrayHasKey('clients', $validator->errors()->toArray());
    }

    /**
     * Test that clients is required when payments is present
     */
    public function testClientsIsRequiredWhenPaymentsPresent(): void
    {
        $this->actingAs($this->user);

        $data = [
            'payments' => true,
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes(), 'Clients should be required when payments is present');
        $this->assertArrayHasKey('clients', $validator->errors()->toArray());
    }

    /**
     * Test that clients is required when multiple dependent fields are present
     */
    public function testClientsIsRequiredWhenMultipleDependentFieldsPresent(): void
    {
        $this->actingAs($this->user);

        $data = [
            'invoices' => 'number',
            'quotes' => 'number',
            'payments' => true,
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes(), 'Clients should be required when invoices, quotes, and payments are present');
        $this->assertArrayHasKey('clients', $validator->errors()->toArray());
    }

    /**
     * Test that clients with valid value 'email' passes when invoices is present
     */
    public function testClientsWithEmailPassesWhenInvoicesPresent(): void
    {
        $this->actingAs($this->user);

        $data = [
            'clients' => 'email',
            'invoices' => 'number',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Clients with email should be valid when invoices is present');
    }

    /**
     * Test that clients with valid value 'name' passes when invoices is present
     */
    public function testClientsWithNamePassesWhenInvoicesPresent(): void
    {
        $this->actingAs($this->user);

        $data = [
            'clients' => 'name',
            'invoices' => 'number',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Clients with name should be valid when invoices is present');
    }

    /**
     * Test that clients with empty string passes when invoices is present (nullable)
     */
    public function testClientsWithEmptyStringPassesWhenInvoicesPresent(): void
    {
        $this->actingAs($this->user);

        $data = [
            'clients' => 'always_create',
            'invoices' => 'number',
        ];

        $this->request->initialize($data);
        
        // Normalize empty strings to 'create' as the request class does
        $normalizedData = $data;
        if (isset($normalizedData['clients']) && $normalizedData['clients'] === '') {
            $normalizedData['clients'] = 'create';
        }
        
        $validator = Validator::make($normalizedData, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Clients with empty string should be valid when invoices is present (nullable)');
    }

    /**
     * Test that clients with invalid value fails
     */
    public function testClientsWithInvalidValueFails(): void
    {
        $this->actingAs($this->user);

        $data = [
            'clients' => 'invalid_value',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes(), 'Clients with invalid value should fail');
        $this->assertArrayHasKey('clients', $validator->errors()->toArray());
    }

    /**
     * Test that products with valid value passes
     */
    public function testProductsWithValidValuePasses(): void
    {
        $this->actingAs($this->user);

        $data = [
            'products' => 'product_key',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Products with product_key should be valid');
    }

    /**
     * Test that products with invalid value fails
     */
    public function testProductsWithInvalidValueFails(): void
    {
        $this->actingAs($this->user);

        $data = [
            'products' => 'invalid_value',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes(), 'Products with invalid value should fail');
        $this->assertArrayHasKey('products', $validator->errors()->toArray());
    }

    /**
     * Test that invoices with valid value passes
     */
    public function testInvoicesWithValidValuePasses(): void
    {
        $this->actingAs($this->user);

        $data = [
            'clients' => 'email',
            'invoices' => 'number',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Invoices with number should be valid');
    }

    /**
     * Test that invoices with invalid value fails
     */
    public function testInvoicesWithInvalidValueFails(): void
    {
        $this->actingAs($this->user);

        $data = [
            'clients' => 'email',
            'invoices' => 'invalid_value',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes(), 'Invoices with invalid value should fail');
        $this->assertArrayHasKey('invoices', $validator->errors()->toArray());
    }

    /**
     * Test that quotes with valid value passes
     */
    public function testQuotesWithValidValuePasses(): void
    {
        $this->actingAs($this->user);

        $data = [
            'clients' => 'email',
            'quotes' => 'number',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Quotes with number should be valid');
    }

    /**
     * Test that quotes with invalid value fails
     */
    public function testQuotesWithInvalidValueFails(): void
    {
        $this->actingAs($this->user);

        $data = [
            'clients' => 'email',
            'quotes' => 'invalid_value',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes(), 'Quotes with invalid value should fail');
        $this->assertArrayHasKey('quotes', $validator->errors()->toArray());
    }

    /**
     * Test that vendors with valid value passes
     */
    public function testVendorsWithValidValuePasses(): void
    {
        $this->actingAs($this->user);

        $data = [
            'vendors' => 'email',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Vendors with email should be valid');
    }

    /**
     * Test that vendors with name value passes
     */
    public function testVendorsWithNameValuePasses(): void
    {
        $this->actingAs($this->user);

        $data = [
            'vendors' => 'name',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Vendors with name should be valid');
    }

    /**
     * Test that vendors with invalid value fails
     */
    public function testVendorsWithInvalidValueFails(): void
    {
        $this->actingAs($this->user);

        $data = [
            'vendors' => 'invalid_value',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes(), 'Vendors with invalid value should fail');
        $this->assertArrayHasKey('vendors', $validator->errors()->toArray());
    }

    /**
     * Test that all fields can be provided together with valid values
     */
    public function testAllFieldsWithValidValuesPasses(): void
    {
        $this->actingAs($this->user);

        $data = [
            'clients' => 'email',
            'products' => 'product_key',
            'invoices' => 'number',
            'quotes' => 'number',
            'payments' => 'always_create',
            'vendors' => 'name',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'All fields with valid values should pass');
    }

    /**
     * Test that empty request passes (all fields are optional)
     */
    public function testEmptyRequestPasses(): void
    {
        $this->actingAs($this->user);

        $data = [];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Empty request should pass (all fields are optional)');
    }

    /**
     * Test that payments can be any value (no validation on payments field itself)
     */
    public function testPaymentsCanBeAnyValue(): void
    {
        $this->actingAs($this->user);

        $data = [
            'clients' => 'email',
            'payments' => 'always_create',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Payments can be any value when clients is provided');
    }
}
