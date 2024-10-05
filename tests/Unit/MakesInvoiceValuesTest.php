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

namespace Tests\Unit;

use Tests\TestCase;

/**
 * 
 *   App\Utils\Traits\MakesInvoiceValues
 */
class MakesInvoiceValuesTest extends TestCase
{
    public function testStrReplaceArray()
    {
        $columns = ['custom_invoice_label3'];

        $columns = str_replace(
            ['custom_invoice_label1',
                'custom_invoice_label2',
                'custom_invoice_label3',
                'custom_invoice_label4', ],
            ['custom_invoice_value1',
                'custom_invoice_value2',
                'custom_invoice_value3',
                'custom_invoice_value4', ],
            $columns
        );

        $this->assertTrue(in_array('custom_invoice_value3', $columns));
        $this->assertFalse(in_array('custom_invoice_value1', $columns));
    }

    public function testFilteringItemTaxes()
    {
        $taxes = collect();

        $tax_name = 'GST';
        $tax_rate = '10.00';

        $key = str_replace(' ', '', $tax_name.$tax_rate);

        $group_tax = collect(['key' => $key, 'total' => 20, 'tax_name' => $tax_name.' '.$tax_rate.'%']);
        $taxes->push($group_tax);
        $group_tax = collect(['key' => $key, 'total' => 30, 'tax_name' => $tax_name.' '.$tax_rate.'%']);
        $taxes->push($group_tax);
        $group_tax = collect(['key' => $key, 'total' => 30, 'tax_name' => $tax_name.' '.$tax_rate.'%']);
        $taxes->push($group_tax);
        $group_tax = collect(['key' => $key, 'total' => 20, 'tax_name' => $tax_name.' '.$tax_rate.'%']);
        $taxes->push($group_tax);
        $group_tax = collect(['key' => 'VAT', 'total' => 20, 'tax_name' => 'VAT'.' '.'17.5%']);
        $taxes->push($group_tax);

        $this->assertEquals(5, $taxes->count());

        $keys = $taxes->pluck('key')->unique();

        $this->assertEquals('GST10.00', $keys->first());

        $tax_array = [];

        foreach ($keys as $key) {
            $tax_name = $taxes->filter(function ($value, $k) use ($key) {
                return $value['key'] == $key;
            })->pluck('tax_name')->first();

            $total_line_tax = $taxes->filter(function ($value, $k) use ($key) {
                return $value['key'] == $key;
            })->sum('total');

            $tax_array[] = ['name' => $tax_name, 'total' => $total_line_tax];
        }

        //$this->assertEquals("GST10.00", print_r($tax_array));
        $this->assertEquals(100, $tax_array[0]['total']);
    }
}
