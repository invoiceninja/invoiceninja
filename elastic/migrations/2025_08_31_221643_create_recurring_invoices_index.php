<?php
declare(strict_types=1);

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;
use Elastic\Elasticsearch\ClientBuilder;

final class CreateRecurringInvoicesIndex implements MigrationInterface
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // Check if index already exists (idempotency)
        $client = ClientBuilder::fromConfig(config('elastic.client.connections.default'));
        if ($client->indices()->exists(['index' => 'recurring_invoices_v2'])) {
            return; // Index already exists, skip creation
        }
        
        $mapping = [
            'properties' => [
                // Core recurring invoice fields
                'id' => ['type' => 'keyword'],
                'name' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'hashed_id' => ['type' => 'keyword'],
                'number' => ['type' => 'keyword'],
                'is_deleted' => ['type' => 'boolean'],
                'amount' => ['type' => 'float'],
                'balance' => ['type' => 'float'],
                'due_date' => ['type' => 'date'],
                'date' => ['type' => 'date'],
                
                // Custom fields
                'custom_value1' => ['type' => 'keyword'],
                'custom_value2' => ['type' => 'keyword'],
                'custom_value3' => ['type' => 'keyword'],
                'custom_value4' => ['type' => 'keyword'],
                
                // Additional fields
                'company_key' => ['type' => 'keyword'],
                'po_number' => ['type' => 'keyword'],
                
                // Line items
                'line_items' => [
                    'type' => 'nested',
                    'properties' => [
                        'quantity' => ['type' => 'float'],
                        'net_cost' => ['type' => 'float'],
                        'cost' => ['type' => 'float'],
                        'product_key' => ['type' => 'text', 'analyzer' => 'standard'],
                        'product_cost' => ['type' => 'float'],
                        'notes' => ['type' => 'text', 'analyzer' => 'standard'],
                        'discount' => ['type' => 'float'],
                        'is_amount_discount' => ['type' => 'boolean'],
                        'tax_name1' => ['type' => 'keyword'],
                        'tax_rate1' => ['type' => 'float'],
                        'tax_name2' => ['type' => 'keyword'],
                        'tax_rate2' => ['type' => 'float'],
                        'tax_name3' => ['type' => 'keyword'],
                        'tax_rate3' => ['type' => 'float'],
                        'sort_id' => ['type' => 'keyword'],
                        'line_total' => ['type' => 'float'],
                        'gross_line_total' => ['type' => 'float'],
                        'tax_amount' => ['type' => 'float'],
                        'date' => ['type' => 'keyword'],
                        'custom_value1' => ['type' => 'keyword'],
                        'custom_value2' => ['type' => 'keyword'],
                        'custom_value3' => ['type' => 'keyword'],
                        'custom_value4' => ['type' => 'keyword'],
                        'type_id' => ['type' => 'keyword'],
                        'tax_id' => ['type' => 'keyword'],
                        'task_id' => ['type' => 'keyword'],
                        'expense_id' => ['type' => 'keyword'],
                        'unit_code' => ['type' => 'keyword'],
                    ]
                ],
            ]
        ];

        Index::createRaw('recurring_invoices_v2', $mapping);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Index::dropIfExists('recurring_invoices_v2');
    }
}
