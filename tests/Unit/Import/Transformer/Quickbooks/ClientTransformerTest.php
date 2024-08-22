<?php

namespace Tests\Unit\Import\Transformer\Quickbooks;

use Tests\TestCase;
use App\Import\Transformer\Quickbooks\ClientTransformer;

class ClientTransformerTest extends TestCase
{
    private $customer_data;
    private $tranformed_data;
    private $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock the company object

        $this->markTestSkipped("NO BUENO");

        $company = (new \App\Factory\CompanyFactory())->create(1234);

        // Read the JSON string from a file and decode into an associative array
        $this->customer_data = json_decode(file_get_contents(app_path('/../tests/Mock/Quickbooks/Data/customer.json')), true);
        $this->transformer = new ClientTransformer($company);
        $this->transformed_data = $this->transformer->transform($this->customer_data['Customer']);
    }

    public function testClassExists()
    {
        $this->assertInstanceOf(ClientTransformer::class, $this->transformer);
    }

    public function testTransformReturnsArray()
    {
        $this->assertIsArray($this->transformed_data);
    }

    public function testTransformHasNameProperty()
    {
        $this->assertArrayHasKey('name', $this->transformed_data);
        $this->assertEquals($this->customer_data['Customer']['CompanyName'], $this->transformed_data['name']);
    }

    public function testTransformHasContactsProperty()
    {
        $this->assertArrayHasKey('contacts', $this->transformed_data);
        $this->assertIsArray($this->transformed_data['contacts']);
        $this->assertArrayHasKey(0, $this->transformed_data['contacts']);
        $this->assertArrayHasKey('email', $this->transformed_data['contacts'][0]);
        $this->assertEquals($this->customer_data['Customer']['PrimaryEmailAddr']['Address'], $this->transformed_data['contacts'][0]['email']);
    }
}
