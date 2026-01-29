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

namespace Tests\Unit\Services\Quickbooks\Transformers;

use Tests\TestCase;
use Tests\MockAccountData;
use App\Models\Company;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Services\Quickbooks\Transformers\CompanyTransformer;

class CompanyTransformerTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private CompanyTransformer $transformer;
    private array $qbCompanyInfo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->transformer = new CompanyTransformer($this->company);

        // Mock QuickBooks IPPCompanyInfo structure based on the payload provided
        $this->qbCompanyInfo = [
            'Id' => '1',
            'SyncToken' => '9',
            'CompanyName' => 'Sandbox Company_US_1',
            'LegalName' => 'Sandbox Company_US_1',
            'CompanyAddr' => [
                'Id' => '1',
                'Line1' => '123 Sierra Way',
                'Line2' => '',
                'Line3' => '',
                'Line4' => '',
                'Line5' => '',
                'City' => 'San Pablo',
                'Country' => 'USA',
                'CountryCode' => '',
                'County' => '',
                'CountrySubDivisionCode' => 'CA',
                'PostalCode' => '87999',
                'PostalCodeSuffix' => '',
            ],
            'CustomerCommunicationAddr' => [
                'Id' => '387',
                'Line1' => '123 Sierra Way',
                'Line2' => '',
                'City' => 'San Pablo',
                'Country' => '',
                'CountryCode' => '',
                'CountrySubDivisionCode' => 'CA',
                'PostalCode' => '87999',
            ],
            'LegalAddr' => [
                'Id' => '386',
                'Line1' => '123 Sierra Way',
                'Line2' => '',
                'City' => 'San Pablo',
                'Country' => '',
                'CountryCode' => '',
                'CountrySubDivisionCode' => 'CA',
                'PostalCode' => '87999',
            ],
            'CompanyEmailAddr' => null,
            'CustomerCommunicationEmailAddr' => [
                'Id' => '',
                'Address' => 'david@invoiceninja.com',
                'Default' => null,
                'Tag' => '',
            ],
            'CompanyURL' => '',
            'PrimaryPhone' => [
                'Id' => '',
                'DeviceType' => '',
                'CountryCode' => '',
                'AreaCode' => '',
                'ExchangeCode' => '',
                'Extension' => '',
                'FreeFormNumber' => '4081234567',
                'Default' => null,
                'Tag' => '',
            ],
            'Email' => [
                'Id' => '',
                'Address' => 'david@invoiceninja.com',
                'Default' => null,
                'Tag' => '',
            ],
            'WebAddr' => '',
            'Country' => 'US',
            'DefaultTimeZone' => 'America/Los_Angeles',
            'SupportedLanguages' => 'en',
        ];
    }

    public function testTransformerInstance(): void
    {
        $this->assertInstanceOf(CompanyTransformer::class, $this->transformer);
    }

    public function testTransformReturnsArray(): void
    {
        $result = $this->transformer->transform($this->qbCompanyInfo);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('quickbooks', $result);
        $this->assertArrayHasKey('settings', $result);
    }

    public function testQuickbooksDataStructure(): void
    {
        $result = $this->transformer->transform($this->qbCompanyInfo);

        $this->assertArrayHasKey('companyName', $result['quickbooks']);
        $this->assertEquals('Sandbox Company_US_1', $result['quickbooks']['companyName']);
    }

    public function testSettingsDataStructure(): void
    {
        $result = $this->transformer->transform($this->qbCompanyInfo);

        $settings = $result['settings'];

        $this->assertArrayHasKey('address1', $settings);
        $this->assertArrayHasKey('address2', $settings);
        $this->assertArrayHasKey('city', $settings);
        $this->assertArrayHasKey('state', $settings);
        $this->assertArrayHasKey('postal_code', $settings);
        $this->assertArrayHasKey('country_id', $settings);
        $this->assertArrayHasKey('phone', $settings);
        $this->assertArrayHasKey('email', $settings);
        $this->assertArrayHasKey('website', $settings);
        $this->assertArrayHasKey('timezone_id', $settings);
    }

    public function testAddressMapping(): void
    {
        $result = $this->transformer->transform($this->qbCompanyInfo);

        $settings = $result['settings'];

        // Should use CompanyAddr as primary
        $this->assertEquals('123 Sierra Way', $settings['address1']);
        $this->assertEquals('San Pablo', $settings['city']);
        $this->assertEquals('CA', $settings['state']);
        $this->assertEquals('87999', $settings['postal_code']);
    }

    public function testContactInformationMapping(): void
    {
        $result = $this->transformer->transform($this->qbCompanyInfo);

        $settings = $result['settings'];

        $this->assertEquals('4081234567', $settings['phone']);
        $this->assertEquals('david@invoiceninja.com', $settings['email']);
    }

    public function testCountryResolution(): void
    {
        $result = $this->transformer->transform($this->qbCompanyInfo);

        $settings = $result['settings'];

        // Country should be resolved to a valid country_id
        $this->assertNotEmpty($settings['country_id']);
        $this->assertIsString($settings['country_id']);
    }

    public function testTimezoneResolution(): void
    {
        $result = $this->transformer->transform($this->qbCompanyInfo);

        $settings = $result['settings'];

        // Timezone should be resolved to a valid timezone_id
        $this->assertNotEmpty($settings['timezone_id']);
        $this->assertIsString($settings['timezone_id']);
    }

    public function testCanPersistQuickbooksData(): void
    {
        $result = $this->transformer->transform($this->qbCompanyInfo);

        // Get fresh company instance
        $company = Company::find($this->company->id);

        // Update quickbooks data
        $company->quickbooks->companyName = $result['quickbooks']['companyName'];

        // Should not throw exception
        $company->save();

        // Verify it was saved
        $company->refresh();
        $this->assertEquals('Sandbox Company_US_1', $company->quickbooks->companyName);
    }

    public function testCanPersistSettingsData(): void
    {
        $result = $this->transformer->transform($this->qbCompanyInfo);

        // Get fresh company instance
        $company = Company::find($this->company->id);

        // Merge settings data
        $company->saveSettings($result['settings'], $company);

        // Should not throw exception
        $company->save();

        // Verify settings were saved
        $company->refresh();
        $this->assertEquals('123 Sierra Way', $company->settings->address1);
        $this->assertEquals('San Pablo', $company->settings->city);
        $this->assertEquals('CA', $company->settings->state);
        $this->assertEquals('87999', $company->settings->postal_code);
        $this->assertEquals('4081234567', $company->settings->phone);
        $this->assertEquals('david@invoiceninja.com', $company->settings->email);
    }

    public function testCanPersistBothQuickbooksAndSettings(): void
    {
        $result = $this->transformer->transform($this->qbCompanyInfo);

        // Get fresh company instance
        $company = Company::find($this->company->id);

        // Update both quickbooks and settings
        $company->quickbooks->companyName = $result['quickbooks']['companyName'];
        $company->saveSettings($result['settings'], $company);

        // Should not throw exception
        $company->save();

        // Verify both were saved
        $company->refresh();
        $this->assertEquals('Sandbox Company_US_1', $company->quickbooks->companyName);
        $this->assertEquals('123 Sierra Way', $company->settings->address1);
        $this->assertEquals('david@invoiceninja.com', $company->settings->email);
    }

    public function testAddressFallbackToLegalAddr(): void
    {
        // Remove CompanyAddr to test fallback
        $qbData = $this->qbCompanyInfo;
        unset($qbData['CompanyAddr']);

        $result = $this->transformer->transform($qbData);

        // Should fallback to LegalAddr
        $this->assertEquals('123 Sierra Way', $result['settings']['address1']);
        $this->assertEquals('San Pablo', $result['settings']['city']);
    }

    public function testEmailFallback(): void
    {
        // Remove Email to test fallback to CustomerCommunicationEmailAddr
        $qbData = $this->qbCompanyInfo;
        unset($qbData['Email']);

        $result = $this->transformer->transform($qbData);

        // Should fallback to CustomerCommunicationEmailAddr
        $this->assertEquals('david@invoiceninja.com', $result['settings']['email']);
    }

    public function testHandlesEmptyData(): void
    {
        $emptyData = [
            'CompanyName' => '',
            'LegalName' => 'Test Legal Name',
        ];

        $result = $this->transformer->transform($emptyData);

        // Should use LegalName when CompanyName is empty
        $this->assertEquals('Test Legal Name', $result['quickbooks']['companyName']);

        // Settings should have empty strings for missing data
        $this->assertEquals('', $result['settings']['address1']);
        $this->assertEquals('', $result['settings']['phone']);
        $this->assertEquals('', $result['settings']['email']);
    }
}
