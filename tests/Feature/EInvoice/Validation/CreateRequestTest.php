<?php

namespace Tests\Feature\EInvoice\Validation;

use App\Models\Country;
use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\EInvoice\Peppol\StoreEntityRequest;

class CreateRequestTest extends TestCase
{
    protected StoreEntityRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $germany = new Country();
        $germany->setRawAttributes([
            'id' => 276,
            'iso_3166_2' => 'DE',
            'name' => 'Germany',
        ], true);
        app()->instance('countries', collect([$germany]));
        $this->request = new StoreEntityRequest();
    }

    public function testValidInput()
    {
        $data = [
            'party_name' => 'Test Company',
            'line1' => '123 Test St',
            'city' => 'Test City',
            'country' => 'DE',
            'zip' => '12345',
            'county' => 'Test County',
            'acts_as_sender' => true,
            'acts_as_receiver' => true,
            'tenant_id' => 'testcompanykey',
            'classification' => 'individual',
            'id_number' => 'xx',

        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes());
    }

    public function testInvalidCountry()
    {
        $data = [
            'party_name' => 'Test Company',
            'line1' => '123 Test St',
            'city' => 'Test City',
            'country' => 999,
            'zip' => '12345',
            'county' => 'Test County',
            'acts_as_sender' => true,
            'acts_as_receiver' => true,
            'tenant_id' => 'testcompanykey',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('country', $validator->errors()->toArray());
    }

    public function testMissingRequiredFields()
    {
        $data = [
            'line2' => 'Optional line',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

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
        $data = [
            'party_name' => 'Test Company',
            'line1' => '123 Test St',
            'line2' => 'Optional line',
            'city' => 'Test City',
            'country' => 'AT',
            'zip' => '12345',
            'county' => 'Test County',
            'tenant_id' => 'testcompanykey',
            'acts_as_sender' => true,
            'acts_as_receiver' => true,
            'classification' => 'business',
            'vat_number' => '234234',
        ];

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes());
    }

    public function testCountryPreparation()
    {
        $data = [
            'country' => '276', // Numeric code for Germany
        ];

        $request = new StoreEntityRequest();
        $request->initialize($data);
        $request->prepareForValidation();

        $this->assertEquals('DE', $request->input('country'));
    }

    public function testFrenchBusinessRequiresAVatNumberWithAValidSiren(): void
    {
        $data = $this->validFrenchBusinessData();
        $data['vat_number'] = 'FR00123456789';

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('vat_number', $validator->errors()->toArray());
    }

    public function testFrenchBusinessAcceptsAValidSirenDerivedFromVat(): void
    {
        $data = $this->validFrenchBusinessData();

        $this->request->initialize($data);
        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes());
    }

    /**
     * @return array<string, mixed>
     */
    private function validFrenchBusinessData(): array
    {
        return [
            'party_name' => 'French Test Company',
            'line1' => '1 Rue de Test',
            'city' => 'Paris',
            'country' => 'FR',
            'zip' => '75001',
            'county' => 'Paris',
            'acts_as_sender' => true,
            'acts_as_receiver' => true,
            'tenant_id' => 'french-test-company',
            'classification' => 'business',
            'vat_number' => 'FR44732829320',
        ];
    }
}
