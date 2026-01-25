<?php
declare(strict_types=1);

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;
use Elastic\Elasticsearch\ClientBuilder;

final class CreateClientsIndex implements MigrationInterface
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // Check if index already exists (idempotency)
        $client = ClientBuilder::fromConfig(config('elastic.client.connections.default'));
        if ($client->indices()->exists(['index' => 'clients_v2'])) {
            return; // Index already exists, skip creation
        }

        $mapping = [
            'properties' => [
                // Core client fields
                'id' => ['type' => 'keyword'],
                'name' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'hashed_id' => ['type' => 'keyword'],
                'number' => ['type' => 'keyword'],
                'is_deleted' => ['type' => 'boolean'],
                'user_id' => ['type' => 'keyword'],
                'assigned_user_id' => ['type' => 'keyword'],
                'company_id' => ['type' => 'keyword'],
                
                // Contact and business information
                'website' => ['type' => 'keyword'],
                'phone' => ['type' => 'keyword'],
                'client_hash' => ['type' => 'keyword'],
                'routing_id' => ['type' => 'keyword'],
                'vat_number' => ['type' => 'keyword'],
                'id_number' => ['type' => 'keyword'],
                'classification' => ['type' => 'keyword'],
                
                // Financial information
                'balance' => ['type' => 'float'],
                'paid_to_date' => ['type' => 'float'],
                'credit_balance' => ['type' => 'float'],
                'payment_balance' => ['type' => 'float'],
                
                // Address information
                'address1' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'address2' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'city' => ['type' => 'keyword'],
                'state' => ['type' => 'keyword'],
                'postal_code' => ['type' => 'keyword'],
                'country_id' => ['type' => 'keyword'],
                
                // Shipping address
                'shipping_address1' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'shipping_address2' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'shipping_city' => ['type' => 'keyword'],
                'shipping_state' => ['type' => 'keyword'],
                'shipping_postal_code' => ['type' => 'keyword'],
                'shipping_country_id' => ['type' => 'keyword'],
                
                // Classification and industry
                'industry_id' => ['type' => 'keyword'],
                'size_id' => ['type' => 'keyword'],
                'group_settings_id' => ['type' => 'keyword'],
                
                // Custom fields
                'custom_value1' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'custom_value2' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'custom_value3' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'custom_value4' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                
                // Notes and content
                'private_notes' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'public_notes' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'display_name' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                
                // Tax and invoice settings
                'is_tax_exempt' => ['type' => 'boolean'],
                'has_valid_vat_number' => ['type' => 'boolean'],
                'tax_info' => ['type' => 'object'],
                'e_invoice' => ['type' => 'object'],
                
                // Settings and configuration
                'settings' => ['type' => 'object'],
                'sync' => ['type' => 'object'],
                
                // Timestamps
                'last_login' => ['type' => 'date'],
                'created_at' => ['type' => 'date'],
                'updated_at' => ['type' => 'date'],
                'archived_at' => ['type' => 'date'],
                
                // Company key for multi-tenancy
                'company_key' => ['type' => 'keyword'],
            ]
        ];

        Index::createRaw('clients_v2', $mapping);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Index::dropIfExists('clients_v2');
    }
}



