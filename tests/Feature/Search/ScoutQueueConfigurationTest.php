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

namespace Tests\Feature\Search;

use App\Models\Client;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScoutQueueConfigurationTest extends TestCase
{
    public function test_null_scout_driver_does_not_dispatch_search_jobs(): void
    {
        $this->assertNull(config('scout.driver'));
        $this->assertFalse(config('scout.queue'));

        Queue::fake();

        $client = new Client();

        $client->searchable();
        $client->unsearchable();

        Queue::assertNothingPushed();
    }
}
