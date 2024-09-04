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

namespace Tests\Unit\Tax;

use App\Services\Tax\VatNumberCheck;
use Tests\TestCase;

/**
 * @test App\Services\Tax\VatNumberCheck
 */
class VatNumberTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testVatNumber()
    {
        // Usage example
        $country_code = "IE"; // Ireland
        $vat_number = "1234567L"; // Example VAT number
        $result = '';

        $vat_checker = new VatNumberCheck($vat_number, $country_code);
        $result = $vat_checker->run();

        $this->assertFalse($result->isValid());
    }

    public function testValidVatNumber()
    {
        // Usage example
        $country_code = "AT"; // Ireland
        $vat_number = "U12345678"; // Example VAT number
        $result = '';

        $vat_checker = new VatNumberCheck($vat_number, $country_code);
        $result = $vat_checker->run();

        $this->assertFalse($result->isValid());
    }
}
