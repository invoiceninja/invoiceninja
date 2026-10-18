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

namespace Tests\Feature\Bank;

use App\Http\Controllers\Bank\YodleeController;
use App\Models\BankIntegration;
use App\Models\Company;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Exercises the reconnect upsert: reviving / updating / preserving BankIntegrations
 * when Yodlee returns an account, independent of the Yodlee API.
 */
class YodleeReconnectTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const BANK_ACCOUNT_ID = 778899;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function yodleeAccount(array $overrides = []): array
    {
        return array_merge([
            'id' => self::BANK_ACCOUNT_ID,
            'account_type' => 'bank',
            'account_name' => 'Primary Checking',
            'account_status' => 'ACTIVE',
            'account_number' => '**** 1234567',
            'provider_account_id' => 5001,
            'provider_id' => 18769,
            'provider_name' => 'Test Bank',
            'nickname' => 'Checking',
            'current_balance' => 1234.56,
            'account_currency' => 'USD',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedIntegration(Company $company, array $overrides = []): BankIntegration
    {
        return BankIntegration::factory()->create(array_merge([
            'company_id' => $company->id,
            'account_id' => $company->account_id,
            'user_id' => $company->owner()?->id ?? $this->user->id,
            'bank_account_id' => self::BANK_ACCOUNT_ID,
            'integration_type' => BankIntegration::INTEGRATION_TYPE_YODLEE,
            'is_deleted' => false,
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $account
     */
    private function upsert(Company $company, array $account): void
    {
        $controller = app(YodleeController::class);

        $method = new ReflectionMethod($controller, 'upsertBankIntegration');
        $method->invoke($controller, $company, $account);
    }

    public function testCreatesIntegrationWhenNoneExists(): void
    {
        $this->upsert($this->company, $this->yodleeAccount());

        $rows = BankIntegration::withTrashed()
            ->where('company_id', $this->company->id)
            ->where('bank_account_id', self::BANK_ACCOUNT_ID)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertFalse((bool) $rows->first()->is_deleted);
    }

    public function testUpdatesLiveIntegrationWithoutDuplicating(): void
    {
        $existing = $this->seedIntegration($this->company, ['balance' => 1, 'disabled_upstream' => true]);

        $this->upsert($this->company, $this->yodleeAccount(['current_balance' => 999.99]));

        $rows = BankIntegration::withTrashed()
            ->where('company_id', $this->company->id)
            ->where('bank_account_id', self::BANK_ACCOUNT_ID)
            ->get();

        $this->assertCount(1, $rows);
        $existing->refresh();
        $this->assertEquals(999.99, $existing->balance);
        $this->assertFalse((bool) $existing->disabled_upstream);
    }

    public function testReanimatesArchivedIntegration(): void
    {
        $archived = $this->seedIntegration($this->company, ['disabled_upstream' => true]);
        $archived->delete(); // deleted_at set, is_deleted stays 0 (archived)

        $this->assertTrue($archived->fresh()->trashed());

        $this->upsert($this->company, $this->yodleeAccount());

        // Reused in place: revived, re-enabled, no new row.
        $archived->refresh();
        $this->assertFalse($archived->trashed());
        $this->assertFalse((bool) $archived->disabled_upstream);

        $this->assertEquals(1, BankIntegration::withTrashed()
            ->where('company_id', $this->company->id)
            ->where('bank_account_id', self::BANK_ACCOUNT_ID)
            ->count());
    }

    public function testReanimatesExplicitlyDeletedIntegration(): void
    {
        $deleted = $this->seedIntegration($this->company, ['is_deleted' => true]);
        $deleted->delete();

        $this->assertTrue($deleted->fresh()->trashed());

        $this->upsert($this->company, $this->yodleeAccount());

        // The deleted shell is reused (reanimated), not duplicated.
        $rows = BankIntegration::withTrashed()
            ->where('company_id', $this->company->id)
            ->where('bank_account_id', self::BANK_ACCOUNT_ID)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertFalse((bool) $rows->first()->is_deleted);
        $this->assertFalse($rows->first()->trashed());
    }

    public function testPrefersLiveRowOverArchivedDuplicate(): void
    {
        $archived = $this->seedIntegration($this->company);
        $archived->delete();

        $live = $this->seedIntegration($this->company, ['balance' => 1]);

        $this->upsert($this->company, $this->yodleeAccount(['current_balance' => 555.00]));

        // The live row is updated; the archived duplicate is left as-is.
        $this->assertEquals(555.00, $live->fresh()->balance);
        $this->assertTrue($archived->fresh()->trashed());
    }

    public function testTwoCompaniesSameAccountStayIndependent(): void
    {
        $company_b = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $integration_b = $this->seedIntegration($company_b, ['balance' => 10]);

        // Reconnecting company A's feed must not touch company B's integration.
        $this->upsert($this->company, $this->yodleeAccount(['current_balance' => 777.00]));

        $this->assertEquals(10, $integration_b->fresh()->balance);

        $this->assertEquals(1, BankIntegration::withTrashed()->where('company_id', $this->company->id)->where('bank_account_id', self::BANK_ACCOUNT_ID)->count());
        $this->assertEquals(1, BankIntegration::withTrashed()->where('company_id', $company_b->id)->where('bank_account_id', self::BANK_ACCOUNT_ID)->count());
    }
}
