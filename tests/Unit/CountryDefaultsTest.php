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

namespace Tests\Unit;

use App\DataMapper\CompanyDefaults\CountryDefaults;
use Tests\TestCase;

class CountryDefaultsTest extends TestCase
{
    public function testGetReturnsDefaultsForUnknownCountry(): void
    {
        $defaults = CountryDefaults::get('999999');

        $this->assertIsArray($defaults);
        $this->assertEmpty($defaults['tax_rates']);
        $this->assertNull($defaults['currency_id']);
        $this->assertNull($defaults['timezone_id']);
        $this->assertNull($defaults['language_id']);
        $this->assertNull($defaults['e_invoice_type']);
        $this->assertNull($defaults['translations']);
        $this->assertNull($defaults['custom_fields']);
        $this->assertEquals(0, $defaults['enabled_tax_rates']);
        $this->assertEquals(1, $defaults['enabled_item_tax_rates']);
    }

    public function testHasReturnsTrueForKnownCountry(): void
    {
        $this->assertTrue(CountryDefaults::has('276')); // Germany
        $this->assertTrue(CountryDefaults::has('36'));  // Australia
        $this->assertTrue(CountryDefaults::has('724')); // Spain
    }

    public function testHasReturnsFalseForUnknownCountry(): void
    {
        $this->assertFalse(CountryDefaults::has('999999'));
    }

    public function testGermanyHasStandardAndReducedRates(): void
    {
        $defaults = CountryDefaults::get('276');

        $this->assertCount(2, $defaults['tax_rates']);
        $this->assertEquals('MwSt', $defaults['tax_rates'][0]['name']);
        $this->assertEquals(19, $defaults['tax_rates'][0]['rate']);
        $this->assertEquals('MwSt (ermäßigt)', $defaults['tax_rates'][1]['name']);
        $this->assertEquals(7, $defaults['tax_rates'][1]['rate']);
        $this->assertEquals('3', $defaults['currency_id']);   // EUR
        $this->assertEquals('37', $defaults['timezone_id']);  // Europe/Berlin
    }

    public function testSpainHasCustomFields(): void
    {
        $defaults = CountryDefaults::get('724');

        $this->assertNotNull($defaults['custom_fields']);
        $this->assertArrayHasKey('contact1', $defaults['custom_fields']);
        $this->assertArrayHasKey('client1', $defaults['custom_fields']);
        $this->assertEquals('7', $defaults['language_id']);
        $this->assertEquals('Facturae_3.2.2', $defaults['e_invoice_type']);
    }

    public function testSpainHasTwoItemTaxRatesAndIrpf(): void
    {
        $defaults = CountryDefaults::get('724');

        $this->assertEquals(2, $defaults['enabled_item_tax_rates']);
        $this->assertCount(3, $defaults['tax_rates']);

        $rates = array_column($defaults['tax_rates'], 'rate', 'name');
        $this->assertEquals(21, $rates['IVA']);
        $this->assertEquals(10, $rates['IVA (reducido)']);
        $this->assertEquals(-15, $rates['IRPF']);
    }

    public function testNoCountryForcesLockInvoices(): void
    {
        foreach (CountryDefaults::countries() as $countryId) {
            $defaults = CountryDefaults::get($countryId);

            $this->assertArrayNotHasKey(
                'lock_invoices',
                $defaults,
                "Country {$countryId} should not force lock_invoices"
            );
        }
    }

    public function testAustraliaHasTranslationsAndBothTaxFlags(): void
    {
        $defaults = CountryDefaults::get('36');

        $this->assertNotNull($defaults['translations']);
        $this->assertEquals('Tax Invoice', $defaults['translations']['invoice']);
        $this->assertEquals(1, $defaults['enabled_tax_rates']);
        $this->assertEquals(1, $defaults['enabled_item_tax_rates']);
        $this->assertCount(1, $defaults['tax_rates']);
        $this->assertEquals('GST', $defaults['tax_rates'][0]['name']);
        $this->assertEquals(10, $defaults['tax_rates'][0]['rate']);
    }

    public function testSouthAfricaHasTranslationsAndBothTaxFlags(): void
    {
        $defaults = CountryDefaults::get('710');

        $this->assertNotNull($defaults['translations']);
        $this->assertEquals('Tax Invoice', $defaults['translations']['invoice']);
        $this->assertEquals(1, $defaults['enabled_tax_rates']);
        $this->assertEquals(1, $defaults['enabled_item_tax_rates']);
    }

    public function testNewZealandHasInvoiceLevelTax(): void
    {
        $defaults = CountryDefaults::get('554');

        $this->assertEquals(1, $defaults['enabled_tax_rates']);
        $this->assertCount(1, $defaults['tax_rates']);
        $this->assertEquals('GST', $defaults['tax_rates'][0]['name']);
        $this->assertEquals(15, $defaults['tax_rates'][0]['rate']);
    }

    public function testUnitedStatesHasItemTaxEnabled(): void
    {
        $defaults = CountryDefaults::get('840');

        $this->assertEquals(1, $defaults['enabled_item_tax_rates']);
        $this->assertEmpty($defaults['tax_rates']);
    }

    public function testCanadaHasMultipleTaxRates(): void
    {
        $defaults = CountryDefaults::get('124');

        $this->assertCount(3, $defaults['tax_rates']);
        $names = array_column($defaults['tax_rates'], 'name');
        $this->assertContains('GST', $names);
        $this->assertContains('QST', $names);
        $this->assertContains('HST', $names);
    }

    public function testAllEntriesHaveValidTaxRates(): void
    {
        foreach (CountryDefaults::countries() as $countryId) {
            $defaults = CountryDefaults::get($countryId);

            foreach ($defaults['tax_rates'] as $index => $taxRate) {
                $this->assertArrayHasKey('name', $taxRate, "Country {$countryId} tax rate {$index} missing 'name'");
                $this->assertArrayHasKey('rate', $taxRate, "Country {$countryId} tax rate {$index} missing 'rate'");
                $this->assertIsString($taxRate['name'], "Country {$countryId} tax rate {$index} 'name' must be string");
                $this->assertIsNumeric($taxRate['rate'], "Country {$countryId} tax rate {$index} 'rate' must be numeric");
                $this->assertNotEquals(0, $taxRate['rate'], "Country {$countryId} tax rate {$index} 'rate' must not be 0");
            }
        }
    }

    public function testAllEntriesHaveCurrencyAndTimezoneIds(): void
    {
        foreach (CountryDefaults::countries() as $countryId) {
            $defaults = CountryDefaults::get($countryId);

            $this->assertNotNull(
                $defaults['currency_id'],
                "Country {$countryId} is missing currency_id"
            );
            $this->assertNotNull(
                $defaults['timezone_id'],
                "Country {$countryId} is missing timezone_id"
            );
        }
    }

    public function testCountriesReturnsAllConfiguredIds(): void
    {
        $countries = CountryDefaults::countries();

        $this->assertNotEmpty($countries);
        $this->assertContains('276', $countries); // Germany
        $this->assertContains('36', $countries);  // Australia
        $this->assertContains('840', $countries); // US
    }
}
