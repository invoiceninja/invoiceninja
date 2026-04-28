<?php
declare(strict_types=1);

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;
use Elastic\Elasticsearch\ClientBuilder;

final class CreateProjectsIndex implements MigrationInterface
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // Check if index already exists (idempotency)
        $client = ClientBuilder::fromConfig(config('elastic.client.connections.default'));
        
        $indexExistsResponse = $client->indices()->exists(['index' => 'projects']);
        if ($indexExistsResponse->getStatusCode() === 200) {
            return;
        }


        $mapping = [
            'properties' => [
                // Core project fields
                'id' => ['type' => 'keyword'],
                'name' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'is_deleted' => ['type' => 'boolean'],
                'hashed_id' => ['type' => 'keyword'],
                'number' => ['type' => 'keyword'],
                'description' => [
                    'type' => 'text',
                    'analyzer' => 'standard'
                ],
                'budgeted_hours' => ['type' => 'float'],
                'task_rate' => ['type' => 'float'],
                'due_date' => ['type' => 'date'],
                'start_date' => ['type' => 'date'],
                'current_hours' => ['type' => 'float'],
                // Custom fields
                'custom_value1' => ['type' => 'keyword'],
                'custom_value2' => ['type' => 'keyword'],
                'custom_value3' => ['type' => 'keyword'],
                'custom_value4' => ['type' => 'keyword'],
                
                // Additional fields
                'company_key' => ['type' => 'keyword'],
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

        Index::createRaw('projects', $mapping);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Index::dropIfExists('projects');
    }
}
