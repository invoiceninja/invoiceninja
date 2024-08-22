<?php

namespace Tests\Unit\Import\Transformer\Quickbooks;

use Tests\TestCase;
use App\Import\Transformer\Quickbooks\ProductTransformer;

class ProductTransformerTest extends TestCase
{
    private $product_data;
    private $tranformed_data;
    private $transformer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped("NO BUENO");

        // Mock the company object
        $company = (new \App\Factory\CompanyFactory())->create(1234);

        // Read the JSON string from a file and decode into an associative array
        $this->product_data = json_decode(file_get_contents(app_path('/../tests/Mock/Quickbooks/Data/item.json')), true);
        $this->transformer = new ProductTransformer($company);
        $this->transformed_data = $this->transformer->transform($this->product_data['Item']);
    }

    public function testClassExists()
    {
        $this->assertInstanceOf(ProductTransformer::class, $this->transformer);
    }

    public function testTransformReturnsArray()
    {
        $this->assertIsArray($this->transformed_data);
    }

    public function testTransformHasProperties()
    {
        $this->assertArrayHasKey('product_key', $this->transformed_data);
        $this->assertArrayHasKey('price', $this->transformed_data);
        $this->assertTrue(is_numeric($this->transformed_data['price']));
        $this->assertEquals(15, (int) $this->transformed_data['price']);
        $this->assertEquals((int) $this->product_data['Item']['QtyOnHand'], $this->transformed_data['quantity']);
    }
}
