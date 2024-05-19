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
use Sabre\Xml\Reader;
use Sabre\Xml\Service;
use Invoiceninja\Einvoice\Models\FACT1\Invoice;

/**
 * @test
 */
class FACT1Test extends TestCase
{

    public array $set = [];
    protected function setUp(): void
    {
        parent::setUp();
    }


//     public function testValidationFact1()
//     {

//         $files = [
//             'tests/Integration/Einvoice/samples/fact1.xml',
//         ];

//         foreach($files as $f) {

//             $xml = file_get_contents($f);

// // Remove namespaces and prefixes
// $xmlWithoutNamespacesAndPrefixes = $this->removeNamespacesAndPrefixes($xml);

// // Output the result
// nlog($xmlWithoutNamespacesAndPrefixes);



// }


    public function removeNamespacesFromArray($data)
    {
        if (is_array($data)) {
            foreach ($data as &$item) {
                if (isset($item['name'])) {
                    // Remove the namespace from the name
                    $item['name'] = preg_replace('/^\{\}(.+)/', '$1', $item['name']);
                }
                if (isset($item['value']) && is_array($item['value'])) {
                    // Recursively process child elements
                    $item['value'] = $this->removeNamespacesFromArray($item['value']);
                }
                if (isset($item['attributes'])) {
                    unset($item['attributes']);
                    // Process attributes if needed
                    // $item['attributes'] = array_map(function ($attr) {
                    //     return preg_replace('/^\{\}(.+)/', '$1', $attr);
                    // }, $item['attributes']);
                }
            }
        }
        return $data;
    }


    
function convertToKeyValue($data)
{
    $result = [];
    foreach ($data as $item) {
        // Remove namespace prefix if present
        $name = preg_replace('/^\{\}(.+)/', '$1', $item['name']);
        $result[$name] = $item['value'];
    }
    return $result;
}


public function keyValueDeserializer(Reader $reader)
{
    $values = [];
    $reader->read();
    $reader->next();
    foreach ($reader->parseGetElements() as $element) {
        // Strip the namespace prefix
        echo "merp".PHP_EOL;
        $name = preg_replace('/^\{\}.*/', '', $element['name']);
        $values[$name] = $element['value'];
    }
    return $values;
}


    public function testFactToArray()
    {
        
        $xml = file_get_contents('tests/Integration/Einvoice/samples/fact1_no_prefixes.xml');
        $service = new Service();
        
        // $service->elementMap = [
        //     '{}' => 'Sabre\Xml\Deserializer\keyValue',
        // ];

        // $service->elementMap = [
        //     '{}*' => function (Reader $reader) use ($service) {
        //         return $this->keyValueDeserializer($reader);
        //     }
        // ];


        $result = $this->removeNamespacesFromArray($service->parse($xml));


        // Convert parsed XML to key-value array
        if (isset($result['value']) && is_array($result['value'])) {
            $keyValueArray = $this->convertToKeyValue($result['value']);
        } else {
            $keyValueArray = [];
        }

        // Output the result
        nlog($keyValueArray);


        //         nlog($cleanedArray);
        nlog($service->parse($xml));

    }

            // Output the result
            // ($xmlWithoutNamespaces);
            
            // $reader = new Reader();
            // $service = new Service();
            
            // $service->elementMap = [
            //     '*' => 'Sabre\Xml\Deserializer\keyValue',
            // ];

            // nlog($service->parse($xmlstring));

            // $payload ='';

            // // $reader->xml($xmlstring);
            // // $payload = $reader->parse();

            // // nlog($payload);
            // $validation_array = false;
            // try {
            //     $rules = Invoice::getValidationRules($payload);
            //     nlog($rules);

            //     $this->assertIsArray($rules);

            //     $payload = Invoice::from($payload)->toArray();
            //     nlog($payload);
            //     $this->assertIsArray($payload);

            //     $validation_array = Invoice::validate($payload);

            //     $this->assertIsArray($validation_array);

            // } catch(\Illuminate\Validation\ValidationException $e) {

            //     nlog($e->errors());
            // }

            // $this->assertIsArray($validation_array);

        
    // }

    private function extractName($name): string
    {
        
        $pattern = '/\{[^{}]*\}([^{}]*)/';

        if (preg_match($pattern, $name, $matches)) {
            $extracted = $matches[1];
            return $extracted; 
        }

        return $name;
    }
}