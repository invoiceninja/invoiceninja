<?php
declare(strict_types=1);

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;
use Elastic\Elasticsearch\ClientBuilder;

final class CreateVendorsIndex implements MigrationInterface
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // Check if index already exists (idempotency)
        $client = ClientBuilder::fromConfig(config('elastic.client.connections.default'));
        if ($client->indices()->exists(['index' => 'vendors_v2'])) {
            return; // Index already exists, skip creation
        }

        $mapping = [
            'properties' => [
                // Core vendor fields
                'id' => ['type' => 'keyword'],
                'name' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'is_deleted' => ['type' => 'boolean'],
                'hashed_id' => ['type' => 'keyword'],
                'number' => ['type' => 'keyword'],
                'id_number' => ['type' => 'keyword'],
                'vat_number' => ['type' => 'keyword'],
                
                // Contact information
                'phone' => ['type' => 'keyword'],
                
                // Address fields
                'address1' => ['type' => 'keyword'],
                'address2' => ['type' => 'keyword'],
                'city' => ['type' => 'keyword'],
                'state' => ['type' => 'keyword'],
                'postal_code' => ['type' => 'keyword'],
                
                // Additional fields
                'website' => ['type' => 'keyword'],
                'private_notes' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'public_notes' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                
                // Custom fields
                'custom_value1' => ['type' => 'keyword'],
                'custom_value2' => ['type' => 'keyword'],
                'custom_value3' => ['type' => 'keyword'],
                'custom_value4' => ['type' => 'keyword'],
                
                // Company
                'company_key' => ['type' => 'keyword'],
            ]
        ];

        Index::createRaw('vendors_v2', $mapping);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Index::dropIfExists('vendors_v2');
    }
}
