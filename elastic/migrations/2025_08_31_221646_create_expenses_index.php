<?php
declare(strict_types=1);

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;
use Elastic\Elasticsearch\ClientBuilder;

final class CreateExpensesIndex implements MigrationInterface
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // Check if index already exists (idempotency)
        $client = ClientBuilder::fromConfig(config('elastic.client.connections.default'));
        if ($client->indices()->exists(['index' => 'expenses_v2'])) {
            return; // Index already exists, skip creation
        }

        $mapping = [
            'properties' => [
                // Core expense fields
                'id' => ['type' => 'keyword'],
                'name' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'is_deleted' => ['type' => 'boolean'],
                'hashed_id' => ['type' => 'keyword'],
                'number' => ['type' => 'keyword'],
                'amount' => ['type' => 'float'],
                'tax_amount' => ['type' => 'float'],
                'tax_name1' => ['type' => 'keyword'],
                'tax_rate1' => ['type' => 'float'],
                'tax_name2' => ['type' => 'keyword'],
                'tax_rate2' => ['type' => 'float'],
                'tax_name3' => ['type' => 'keyword'],
                'tax_rate3' => ['type' => 'float'],
                'date' => ['type' => 'date'],
                'payment_date' => ['type' => 'date'],
                
                // Custom fields
                'custom_value1' => ['type' => 'keyword'],
                'custom_value2' => ['type' => 'keyword'],
                'custom_value3' => ['type' => 'keyword'],
                'custom_value4' => ['type' => 'keyword'],
                
                // Additional fields
                'company_key' => ['type' => 'keyword'],
                'category_id' => ['type' => 'keyword'],
                'vendor_id' => ['type' => 'keyword'],
                'client_id' => ['type' => 'keyword'],
                'project_id' => ['type' => 'keyword'],
                'private_notes' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'public_notes' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
            ]
        ];

        Index::createRaw('expenses_v2', $mapping);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Index::dropIfExists('expenses_v2');
    }
}
