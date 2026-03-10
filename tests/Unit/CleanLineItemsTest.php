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

use App\DataMapper\InvoiceItem;
use App\Utils\Traits\CleanLineItems;
use Tests\TestCase;

class CleanLineItemsTest extends TestCase
{
    use CleanLineItems;

    
    public function test_clean_items_returns_empty_array_for_null()
    {
        $this->assertEquals([], $this->cleanItems(null));
    }

    
    public function test_clean_items_returns_empty_array_for_non_array()
    {
        $this->assertEquals([], $this->cleanItems('string'));
    }

    
    public function test_clean_items_returns_empty_array_for_empty_array()
    {
        $this->assertEquals([], $this->cleanItems([]));
    }

    
    public function test_clean_items_processes_multiple_items()
    {
        $items = [
            ['cost' => 10, 'quantity' => 1, 'product_key' => 'A'],
            ['cost' => 20, 'quantity' => 2, 'product_key' => 'B'],
        ];

        $result = $this->cleanItems($items);

        $this->assertCount(2, $result);
        $this->assertEquals(10.0, $result[0]['cost']);
        $this->assertEquals(20.0, $result[1]['cost']);
        $this->assertEquals('A', $result[0]['product_key']);
        $this->assertEquals('B', $result[1]['product_key']);
    }

    
    public function test_missing_keys_get_defaults()
    {
        $result = $this->cleanItems([[]]);
        $item = $result[0];

        $this->assertEquals(0.0, $item['cost']);
        $this->assertEquals(0.0, $item['quantity']);
        $this->assertEquals('', $item['product_key']);
        $this->assertEquals('', $item['notes']);
        $this->assertEquals(0.0, $item['discount']);
        $this->assertFalse($item['is_amount_discount']);
        $this->assertEquals('', $item['tax_name1']);
        $this->assertEquals(0.0, $item['tax_rate1']);
        $this->assertEquals('', $item['tax_name2']);
        $this->assertEquals(0.0, $item['tax_rate2']);
        $this->assertEquals('', $item['tax_name3']);
        $this->assertEquals(0.0, $item['tax_rate3']);
        $this->assertEquals('0', $item['sort_id']);
        $this->assertEquals(0.0, $item['line_total']);
        $this->assertEquals(0.0, $item['gross_line_total']);
        $this->assertEquals(0.0, $item['tax_amount']);
        $this->assertEquals('', $item['date']);
        $this->assertEquals('', $item['custom_value1']);
        $this->assertEquals('', $item['custom_value2']);
        $this->assertEquals('', $item['custom_value3']);
        $this->assertEquals('', $item['custom_value4']);
        $this->assertEquals('', $item['task_id']);
        $this->assertEquals('', $item['expense_id']);
        $this->assertEquals('C62', $item['unit_code']);
    }

    
    public function test_all_properties_from_invoice_item_are_present()
    {
        $result = $this->cleanItems([[]]);
        $item = $result[0];

        $expected_keys = array_keys(InvoiceItem::$casts);

        foreach ($expected_keys as $key) {
            $this->assertArrayHasKey($key, $item, "Missing key: {$key}");
        }
    }

    
    public function test_values_are_cast_to_correct_types()
    {
        $items = [[
            'cost' => '99.99',
            'quantity' => '3',
            'discount' => '10',
            'is_amount_discount' => '1',
            'tax_rate1' => '7.5',
            'line_total' => '269.97',
            'type_id' => 1,
        ]];

        $result = $this->cleanItems($items);
        $item = $result[0];

        $this->assertIsFloat($item['cost']);
        $this->assertSame(99.99, $item['cost']);
        $this->assertIsFloat($item['quantity']);
        $this->assertSame(3.0, $item['quantity']);
        $this->assertIsFloat($item['discount']);
        $this->assertSame(10.0, $item['discount']);
        $this->assertIsBool($item['is_amount_discount']);
        $this->assertTrue($item['is_amount_discount']);
        $this->assertIsFloat($item['tax_rate1']);
        $this->assertSame(7.5, $item['tax_rate1']);
        $this->assertIsString($item['type_id']);
        $this->assertSame('1', $item['type_id']);
    }

    
    public function test_type_id_defaults_to_1_when_missing()
    {
        $result = $this->cleanItems([[]]);

        $this->assertEquals('1', $result[0]['type_id']);
    }

    
    public function test_type_id_zero_is_normalized_to_1()
    {
        $result = $this->cleanItems([['type_id' => '0']]);

        $this->assertEquals('1', $result[0]['type_id']);
    }

    
    public function test_type_id_preserves_valid_values()
    {
        foreach (['1', '2', '3', '4', '5', '6'] as $type) {
            $result = $this->cleanItems([['type_id' => $type]]);
            $this->assertEquals($type, $result[0]['type_id'], "type_id {$type} was not preserved");
        }
    }

    
    public function test_tax_id_defaults_to_1_when_missing()
    {
        $result = $this->cleanItems([[]]);

        $this->assertEquals('1', $result[0]['tax_id']);
    }

    
    public function test_tax_id_defaults_to_1_when_empty_for_products()
    {
        $result = $this->cleanItems([['tax_id' => '', 'type_id' => '1']]);

        $this->assertEquals('1', $result[0]['tax_id']);
    }

    
    public function test_tax_id_defaults_to_2_when_empty_for_services()
    {
        $result = $this->cleanItems([['tax_id' => '', 'type_id' => '2']]);

        $this->assertEquals('2', $result[0]['tax_id']);
    }

    
    public function test_tax_id_preserves_valid_value()
    {
        $result = $this->cleanItems([['tax_id' => '5']]);

        $this->assertEquals('5', $result[0]['tax_id']);
    }

    
    public function test_xss_sanitization_on_notes()
    {
        $result = $this->cleanItems([['notes' => '<script>alert("xss")</script>']]);
        $this->assertStringNotContainsString('</sc', $result[0]['notes']);
        $this->assertStringNotContainsString('alert(', $result[0]['notes']);
    }

    
    public function test_xss_sanitization_on_product_key()
    {
        $result = $this->cleanItems([['product_key' => 'test onerror=hack']]);
        $this->assertStringNotContainsString('onerror', $result[0]['product_key']);
    }

    
    public function test_xss_sanitization_on_custom_values()
    {
        $xss_payloads = [
            'custom_value1' => '</script>',
            'custom_value2' => 'onerror=x',
            'custom_value3' => 'prompt(1)',
            'custom_value4' => 'alert(1)',
        ];

        $result = $this->cleanItems([$xss_payloads]);
        $item = $result[0];

        $this->assertStringNotContainsString('</sc', $item['custom_value1']);
        $this->assertStringNotContainsString('onerror', $item['custom_value2']);
        $this->assertStringNotContainsString('prompt(', $item['custom_value3']);
        $this->assertStringNotContainsString('alert(', $item['custom_value4']);
    }

    
    public function test_clean_text_is_not_affected_by_xss_filter()
    {
        $result = $this->cleanItems([['notes' => 'This is a normal product description with no XSS.']]);

        $this->assertEquals('This is a normal product description with no XSS.', $result[0]['notes']);
    }

    
    public function test_id_field_is_removed()
    {
        $result = $this->cleanItems([['id' => 99]]);

        $this->assertArrayNotHasKey('id', $result[0]);
    }

    
    public function test_underscore_id_field_is_removed()
    {
        $result = $this->cleanItems([['_id' => 'abc123']]);

        $this->assertArrayNotHasKey('_id', $result[0]);
    }

    
    public function test_both_id_fields_are_removed()
    {
        $result = $this->cleanItems([['id' => 1, '_id' => 'x']]);

        $this->assertArrayNotHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('_id', $result[0]);
    }

    
    public function test_extra_keys_not_in_invoice_item_are_preserved()
    {
        $result = $this->cleanItems([['some_extra_field' => 'value']]);

        $this->assertArrayHasKey('some_extra_field', $result[0]);
        $this->assertEquals('value', $result[0]['some_extra_field']);
    }

    
    public function test_null_values_are_replaced_with_defaults()
    {
        $result = $this->cleanItems([['cost' => null, 'notes' => null, 'is_amount_discount' => null]]);
        $item = $result[0];

        $this->assertSame(0.0, $item['cost']);
        $this->assertSame('', $item['notes']);
        $this->assertFalse($item['is_amount_discount']);
    }

    
    public function test_object_items_are_handled()
    {
        $obj = new \stdClass();
        $obj->cost = 50;
        $obj->quantity = 2;
        $obj->product_key = 'Widget';

        $result = $this->cleanItems([$obj]);

        $this->assertSame(50.0, $result[0]['cost']);
        $this->assertSame(2.0, $result[0]['quantity']);
        $this->assertSame('Widget', $result[0]['product_key']);
    }

    
    public function test_clean_fee_items_removes_gateway_fee_types()
    {
        $items = [
            ['type_id' => '1', 'cost' => 100],
            ['type_id' => '3', 'cost' => 5],
            ['type_id' => '4', 'cost' => 3],
            ['type_id' => '2', 'cost' => 200],
        ];

        $result = $this->cleanFeeItems($items);

        $this->assertCount(2, $result);
        foreach ($result as $item) {
            $this->assertNotContains($item['type_id'], ['3', '4']);
        }
    }

    
    public function test_clean_fee_items_preserves_non_gateway_types()
    {
        $items = [
            ['type_id' => '1', 'cost' => 100],
            ['type_id' => '2', 'cost' => 200],
            ['type_id' => '5', 'cost' => 50],
            ['type_id' => '6', 'cost' => 75],
        ];

        $result = $this->cleanFeeItems($items);

        $this->assertCount(4, $result);
    }

    
    public function test_entity_total_amount()
    {
        $items = [
            ['cost' => 10, 'quantity' => 2],
            ['cost' => 25, 'quantity' => 4],
        ];

        $reflection = new \ReflectionMethod($this, 'entityTotalAmount');
        $total = $reflection->invoke($this, $items);

        $this->assertEquals(120, $total);
    }

    
    public function test_realistic_invoice_line_items()
    {
        $items = [
            [
                'product_key' => 'WIDGET-001',
                'notes' => 'Premium widget with custom engraving',
                'cost' => '49.99',
                'quantity' => '10',
                'discount' => '5',
                'is_amount_discount' => false,
                'tax_name1' => 'GST',
                'tax_rate1' => '10',
                'type_id' => '1',
                'custom_value1' => 'Blue',
                'custom_value2' => 'Large',
            ],
            [
                'product_key' => 'INSTALL-SVC',
                'notes' => 'Installation service',
                'cost' => '150.00',
                'quantity' => '1',
                'type_id' => '2',
                'tax_id' => '',
            ],
        ];

        $result = $this->cleanItems($items);

        $this->assertCount(2, $result);

        // Product line
        $this->assertSame(49.99, $result[0]['cost']);
        $this->assertSame(10.0, $result[0]['quantity']);
        $this->assertSame(5.0, $result[0]['discount']);
        $this->assertSame('GST', $result[0]['tax_name1']);
        $this->assertSame(10.0, $result[0]['tax_rate1']);
        $this->assertSame('1', $result[0]['type_id']);
        $this->assertSame('Blue', $result[0]['custom_value1']);

        // Service line - tax_id should default to '2' for services
        $this->assertSame(150.0, $result[1]['cost']);
        $this->assertSame('2', $result[1]['type_id']);
        $this->assertSame('2', $result[1]['tax_id']);
    }
}
