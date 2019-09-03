<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\Traits\MakesInvoiceValues
 */
class MakesInvoiceValuesTest extends TestCase
{

	public function testStrReplaceArray()
	{

	$columns = ['custom_invoice_label3'];

    		$columns = str_replace(['custom_invoice_label1', 
    			'custom_invoice_label2', 
    			'custom_invoice_label3',
    			'custom_invoice_label4'], 
    			['custom_invoice_value1',
    			'custom_invoice_value2',
    			'custom_invoice_value3',
    			'custom_invoice_value4'], 
    			$columns);


    	$this->assertTrue(in_array("custom_invoice_value3", $columns));
    	$this->assertFalse(in_array("custom_invoice_value1", $columns));
	}



}
