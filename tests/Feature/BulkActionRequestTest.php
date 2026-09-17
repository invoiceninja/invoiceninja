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

use App\Http\Middleware\PasswordProtection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

class BulkActionRequestTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    /**
     * Endpoints whose Form Requests decode hashed ids and then validate existence.
     *
     * @var array<string, array<int, string>>
     */
    private const HASH_VALIDATED_ENDPOINTS = [
        '/api/v1/designs/bulk' => ['archive', 'restore', 'delete', 'clone'],
        '/api/v1/expense_categories/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/group_settings/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/locations/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/payment_terms/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/products/bulk' => ['archive', 'restore', 'delete', 'set_tax_id'],
        '/api/v1/recurring_expenses/bulk' => ['archive', 'restore', 'delete', 'start', 'stop'],
        '/api/v1/recurring_invoices/bulk' => ['archive', 'restore', 'delete', 'start', 'stop', 'send_now'],
        '/api/v1/task_schedulers/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/tax_rates/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/tokens/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/vendors/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/webhooks/bulk' => ['archive', 'restore', 'delete'],
    ];

    /**
     * Endpoints that constrain action but do not decode/validate hashed ids.
     *
     * @var array<string, array<int, string>>
     */
    private const ACTION_ONLY_ENDPOINTS = [
        '/api/v1/bank_integrations/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/bank_transaction_rules/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/bank_transactions/bulk' => ['archive', 'restore', 'delete', 'convert_matched', 'unlink'],
        '/api/v1/payments/bulk' => ['archive', 'restore', 'delete', 'email', 'email_receipt', 'template'],
        '/api/v1/purchase_orders/bulk' => ['archive', 'restore', 'delete'],
        '/api/v1/quotes/bulk' => ['archive', 'restore', 'delete', 'clone_to_quote'],
        '/api/v1/task_statuses/bulk' => ['archive', 'restore', 'delete'],
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
        foreach (array_keys(self::HASH_VALIDATED_ENDPOINTS) as $endpoint) {
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
        foreach (array_keys($this->allEndpoints()) as $endpoint) {
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
        foreach (self::HASH_VALIDATED_ENDPOINTS as $endpoint => $actions) {
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

    public function testInvoiceAndUserBulkRequestsRejectUnsupportedActions(): void
    {
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/bulk', [
            'action' => 'unsupported',
            'ids' => ['not-a-valid-hash'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['action']);

        $this->withoutMiddleware(PasswordProtection::class);

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/users/bulk', [
            'action' => 'unsupported',
            'ids' => ['not-a-valid-hash'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['action']);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function allEndpoints(): array
    {
        return array_merge(self::HASH_VALIDATED_ENDPOINTS, self::ACTION_ONLY_ENDPOINTS);
    }
}
