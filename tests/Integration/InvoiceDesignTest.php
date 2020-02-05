<?php

namespace Tests\Integration;

use App\Designs\Designer;
use App\Designs\Modern;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Designs\Designer
 */
class InvoiceDesignTest extends TestCase
{
  	use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testDesignExists()
    {

    	$modern = new Modern();

    	$input_variables = [
    		'client_details' => [
				'name',
				'id_number',
				'vat_number',
				'address1',
				'address2',
				'city_state_postal',
				'postal_city_state',
				'country',
				'email',
				'custom_value1',
				'custom_value2',
				'custom_value3',
				'custom_value4',
    		],
    		'company_details' => [
    			'company_name',
				'id_number',
				'vat_number',
				'website',
				'email',
				'phone',
				'custom_value1',
				'custom_value2',
				'custom_value3',
				'custom_value4',
    		],
    		'company_address' => [
				'address1',
				'address2',
				'city_state_postal',
				'postal_city_state',
				'country',
				'custom_value1',
				'custom_value2',
				'custom_value3',
				'custom_value4',
    		],
    		'invoice_details' => [
				'invoice_number',
				'po_number',
				'date',
				'due_date',
				'balance_due',
				'invoice_total',
				'partial_due',
				'custom_value1',
				'custom_value2',
				'custom_value3',
				'custom_value4',
    		],
    		'table_columns' => [
    			'product_key', 
	    		'notes', 
	    		'cost',
	    		'quantity', 
	    		'discount', 
	    		'tax_name1', 
	    		'line_total'
    		],
    	];

    	$designer = new Designer($modern, $input_variables);

    	$html = $designer->build($this->invoice)->getHtml();

    	$this->assertNotNull($html);

    	\Log::error($html);
    }

    
}

            