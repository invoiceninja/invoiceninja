<?php
declare(strict_types=1);

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;
use Elastic\Elasticsearch\ClientBuilder;

final class CreateTasksIndex implements MigrationInterface
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // Check if index already exists (idempotency)
        $client = ClientBuilder::fromConfig(config('elastic.client.connections.default'));
        
        $indexExistsResponse = $client->indices()->exists(['index' => 'tasks']);
        if ($indexExistsResponse->getStatusCode() === 200) {
            return;
        }



        $mapping = [
            'properties' => [
                // Core task fields
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
                'task_rate' => ['type' => 'float'],
                'calculated_start_date' => ['type' => 'date'],
                
                // Custom fields
                'custom_value1' => ['type' => 'keyword'],
                'custom_value2' => ['type' => 'keyword'],
                'custom_value3' => ['type' => 'keyword'],
                'custom_value4' => ['type' => 'keyword'],
                
                // Additional fields
                'company_key' => ['type' => 'keyword'],
                'time_log' => ['type' => 'nested', 'properties' => [
                    'start_time' => ['type' => 'integer'],
                    'end_time' => ['type' => 'integer'],
                    'description' => ['type' => 'text', 'analyzer' => 'standard'],
                    'billable' => ['type' => 'boolean'],
                    'is_running' => ['type' => 'boolean'],
                ]],
               
            ]
        ];

        Index::createRaw('tasks', $mapping);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Index::dropIfExists('tasks');
    }
}

