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
        $pdf_variables = $this->settings->pdf_variables;

        $this->assertEquals(ctrans('texts.client_name'), $pdf_variables->client_details->{'$client.name'});
    }

    public function testPdfVariablesConvertedToArray()
    {
        $pdf_variables = json_decode(json_encode($this->settings->pdf_variables), true);

        $this->assertEquals(ctrans('texts.client_name'), $pdf_variables['client_details']['$client.name']);
    }

    public function testReplaceSampleHeaderText()
    {
        /* this flattens the multi dimensional array so we can do a single str_replace */
        $pdf_variables = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($this->settings->pdf_variables)));

        //\Log::error(print_r($pdf_variables,1));

        $sample_header_text = '<tr><td>$client.name</td><td>$product.product_key</td><td>$product.line_total</td></tr>';

        $replaced_header_text = str_replace(array_keys($pdf_variables), array_values($pdf_variables), $sample_header_text);

        $this->assertEquals($replaced_header_text, '<tr><td>Client Name</td><td>Product</td><td>Line Total</td></tr>');
    }
}
