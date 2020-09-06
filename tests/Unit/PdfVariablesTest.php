<?php

namespace Tests\Unit;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * @test
 */
class PdfVariablesTest extends TestCase
{
    public function setUp() :void
    {
        parent::setUp();

        $this->settings = CompanySettings::defaults();
    }

    public function testPdfVariableDefaults()
    {
        $this->assertTrue(is_array($this->settings->pdf_variables->client_details));
    }
}
