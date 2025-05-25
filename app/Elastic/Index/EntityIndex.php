<?php

namespace App\Elastic\Index;

class EntityIndex 
{

    public array $mapping = [
        'properties' => [
            'id' => [ 'type' => 'text' ],  
            'name' => [ 'type' => 'text' ],   
            'hashed_id' => [ 'type' => 'text' ],
            'number' => [ 'type' => 'text' ],
            'is_deleted' => [ 'type' => 'boolean' ],
            'amount' => [ 'type' => 'long' ],
            'balance' => [ 'type' => 'long' ],
            'due_date' => [ 'type' => 'date' ],
            'date' => [ 'type' => 'date' ],
            'custom_value1' => [ 'type' => 'text' ],
            'custom_value2' => [ 'type' => 'text' ],
            'custom_value3' => [ 'type' => 'text' ],
            'custom_value4' => [ 'type' => 'text' ],
            'company_key' => [ 'type' => 'text' ],
            'po_number' => [ 'type' => 'text' ],
            'line_items' => [
                'type' => 'nested',
                'properties' => [
                    'product_key' => [ 'type' => 'text' ],
                    'notes' => [ 'type' => 'text' ],
                    'cost' => [ 'type' => 'long' ],
                    'product_cost' => [ 'type' => 'long' ],
                    'is_amount_discount' => [ 'type' => 'boolean' ],
                    'line_total' => [ 'type' => 'long' ],
                    'gross_line_total' => [ 'type' => 'long' ],
                    'tax_amount' => [ 'type' => 'long' ],
                    'quantity' => [ 'type' => 'float' ],
                    'discount' => [ 'type' => 'float' ],
                    'tax_name1' => [ 'type' => 'text' ],
                    'tax_rate1' => [ 'type' => 'float' ],
                    'tax_name2' => [ 'type' => 'text' ],
                    'tax_rate2' => [ 'type' => 'float' ],
                    'tax_name3' => [ 'type' => 'text' ],
                    'tax_rate3' => [ 'type' => 'float' ],
                    'custom_value1' => [ 'type' => 'text' ],
                    'custom_value2' => [ 'type' => 'text' ],
                    'custom_value3' => [ 'type' => 'text' ],
                    'custom_value4' => [ 'type' => 'text' ],
                    'type_id' => [ 'type' => 'text' ],
                    'tax_id' => [ 'type' => 'text' ],
                    'task_id' => [ 'type' => 'text' ],
                    'expense_id' => [ 'type' => 'text' ],
                    'unit_code' => [ 'type' => 'text' ],
                ]
            ]
        ]
    ];

    public function create(string $index_name): void 
    {
        \Elastic\Migrations\Facades\Index::createRaw($index_name, $this->mapping);
    }

}