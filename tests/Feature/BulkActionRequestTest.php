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

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

class BulkActionRequestTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    /** @var array<string, array<int, string>> */
    private const ENDPOINT_ACTIONS = [
        '/api/v1/expense_categories/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/group_settings/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/locations/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/recurring_expenses/bulk' => ['archive', 'restore', 'delete', 'start', 'stop'],
        '/api/v1/task_schedulers/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/tax_rates/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/tokens/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/vendors/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/webhooks/bulk' => ['archive', 'restore', 'delete'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();
        Model::reguard();
    }

    public function testBulkRequestsRejectMalformedAndRawIds(): void
    {
        foreach (array_keys(self::ENDPOINT_ACTIONS) as $endpoint) {
            $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson($endpoint, [
                'action' => 'archive',
                'ids' => ['not-a-valid-hash'],
            ])->assertStatus(422)
                ->assertJsonValidationErrors(['ids']);

            $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson($endpoint, [
                'action' => 'archive',
                'ids' => [1],
            ])->assertStatus(422)
                ->assertJsonValidationErrors(['ids']);
        }
    }

    public function testBulkRequestsRejectUnsupportedActions(): void
    {
        foreach (array_keys(self::ENDPOINT_ACTIONS) as $endpoint) {
            $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson($endpoint, [
                'action' => 'unsupported',
                'ids' => ['not-a-valid-hash'],
            ])->assertStatus(422)
                ->assertJsonValidationErrors(['action']);
        }
    }

    public function testBulkRequestsAcceptEverySupportedAction(): void
    {
        foreach (self::ENDPOINT_ACTIONS as $endpoint => $actions) {
            foreach ($actions as $action) {
                $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson($endpoint, [
                    'action' => $action,
                    'ids' => ['not-a-valid-hash'],
                ])->assertStatus(422)
                    ->assertJsonMissingValidationErrors(['action'])
                    ->assertJsonValidationErrors(['ids']);
            }
        }
    }
}
