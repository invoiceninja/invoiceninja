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

namespace Tests\Integration\Einvoice;

use Tests\TestCase;
use Invoiceninja\Einvoice\Models\FACT1\Invoice;

/**
 * @test
 */
class FACT1Test extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testValidationFact1()
    {

        $files = [
            'tests/Integration/Einvoice/samples/fact1.xml',
        ];

        foreach($files as $f) {

            $xmlstring = file_get_contents($f);

// nlog($xmlstring);

            $xml = simplexml_load_string($xmlstring, "SimpleXMLElement");
            $json = json_encode($xml);
            $payload = json_decode($json, true);

            nlog($xml);
            nlog($payload);
            $validation_array = false;
            try {
                $rules = Invoice::getValidationRules($payload);
                nlog($rules);

                $this->assertIsArray($rules);

                $payload = Invoice::from($payload)->toArray();
                nlog($payload);
                $this->assertIsArray($payload);

                $validation_array = Invoice::validate($payload);

                $this->assertIsArray($validation_array);

            } catch(\Illuminate\Validation\ValidationException $e) {

                nlog($e->errors());
            }

            $this->assertIsArray($validation_array);

        }


    }
}