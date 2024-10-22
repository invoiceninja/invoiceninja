<?php

namespace Tests\Feature\EInvoice\Validation;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\EInvoice\Peppol\CreateRequest;
use App\Models\Country;
use Illuminate\Support\Collection;

class CreateRequestTest extends TestCase
{
    protected CreateRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new CreateRequest();
    }

    public function testValidInput()
    {
        $validator = Validator::make([
            'party_name' => 'Test Company',
            'line1' => '123 Test St',
            'city' => 'Test City',
            'country' => 'DE', // Assuming 1 is the ID for Germany
            'zip' => '12345',
            'county' => 'Test County',
        ], $this->request->rules());

        $this->assertTrue($validator->passes());
    }

    public function testInvalidCountry()
    {
        $validator = Validator::make([
            'party_name' => 'Test Company',
            'line1' => '123 Test St',
            'city' => 'Test City',
            'country' => 999, // Invalid country ID
            'zip' => '12345',
            'county' => 'Test County',
        ], $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('country', $validator->errors()->toArray());
    }

    public function testMissingRequiredFields()
    {
        $validator = Validator::make([
            'line2' => 'Optional line',
        ], $this->request->rules());

        $this->assertFalse($validator->passes());
        $errors = $validator->errors()->toArray();
        $this->assertArrayHasKey('party_name', $errors);
        $this->assertArrayHasKey('line1', $errors);
        $this->assertArrayHasKey('city', $errors);
        $this->assertArrayHasKey('country', $errors);
        $this->assertArrayHasKey('zip', $errors);
        $this->assertArrayHasKey('county', $errors);
    }

    public function testOptionalLine2()
    {
        $validator = Validator::make([
            'party_name' => 'Test Company',
            'line1' => '123 Test St',
            'line2' => 'Optional line',
            'city' => 'Test City',
            'country' => 'AT',
            'zip' => '12345',
            'county' => 'Test County',
        ], $this->request->rules());

        $this->assertTrue($validator->passes());
    }

    public function testCountryPreparation()
    {
        $request = new CreateRequest([
            'country' => '276', // Assuming 1 is the ID for Germany
        ]);

        $request->prepareForValidation();

        $this->assertEquals('DE', $request->input('country'));
    }
}
