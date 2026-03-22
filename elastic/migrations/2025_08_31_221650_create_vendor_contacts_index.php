<?php
declare(strict_types=1);

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;
use Elastic\Elasticsearch\ClientBuilder;

final class CreateVendorContactsIndex implements MigrationInterface
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // Check if index already exists (idempotency)
        $client = ClientBuilder::fromConfig(config('elastic.client.connections.default'));
        if ($client->indices()->exists(['index' => 'vendor_contacts_v2'])) {
            return; // Index already exists, skip creation
        }

        $mapping = [
            'properties' => [
                // Core vendor contact fields
                'id' => ['type' => 'keyword'],
                'name' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'is_deleted' => ['type' => 'boolean'],
                'hashed_id' => ['type' => 'keyword'],
                'first_name' => ['type' => 'keyword'],
                'last_name' => ['type' => 'keyword'],
                'email' => ['type' => 'keyword'],
                'phone' => ['type' => 'keyword'],
                'is_primary' => ['type' => 'boolean'],
                
                // Custom fields
                'custom_value1' => ['type' => 'keyword'],
                'custom_value2' => ['type' => 'keyword'],
                'custom_value3' => ['type' => 'keyword'],
                'custom_value4' => ['type' => 'keyword'],
                
                // Additional fields
                'company_key' => ['type' => 'keyword'],
                'vendor_id' => ['type' => 'keyword'],
                'send_email' => ['type' => 'boolean'],
            ]
        ];

        Index::createRaw('vendor_contacts_v2', $mapping);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Index::dropIfExists('vendor_contacts_v2');
    }
}
