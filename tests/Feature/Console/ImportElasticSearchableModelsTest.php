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

namespace Tests\Feature\Console;

use App\Console\Commands\Elastic\ImportElasticSearchableModels;
use App\Jobs\Elastic\ImportElasticSearchableChunk;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use ReflectionClass;
use Tests\TestCase;

class ImportElasticSearchableModelsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rejects_unknown_database_connection(): void
    {
        $this->artisan('elastic:import-all', ['--database' => 'not_a_real_connection'])
            ->expectsOutputToContain('Unknown database connection')
            ->assertFailed();
    }

    public function test_rejects_non_positive_chunk_option(): void
    {
        $this->artisan('elastic:import-all', ['--chunk' => '0'])
            ->expectsOutputToContain('Chunk size must be a positive integer.')
            ->assertFailed();
    }

    public function test_rejects_unknown_model_option(): void
    {
        $this->artisan('elastic:import-all', ['--model' => 'NotSearchable'])
            ->expectsOutputToContain('Unknown searchable model')
            ->assertFailed();
    }
}
