<?php

namespace Tests\Unit\StripeConnect;

use App\Factory\CompanyGatewayFactory;
use App\Libraries\MultiDB;
use App\Models\CompanyGateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Admin\Jobs\Stripe\AccountUpdate;
use Tests\MockAccountData;
use Tests\TestCase;

class AccountUpdateLookupTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private array $databases = [];

    private ?string $originalDatabase = null;

    protected function setUp(): void
    {
        $this->databases = MultiDB::$dbs;

        parent::setUp();

        $this->originalDatabase = config('database.default');

        if (!class_exists(AccountUpdate::class)) {
            $this->markTestSkipped('AccountUpdate job does not exist');
        }

        $this->makeTestData();
        MultiDB::$dbs = [$this->originalDatabase];
    }

    protected function tearDown(): void
    {
        if ($this->databases !== []) {
            MultiDB::$dbs = $this->databases;
        }

        if ($this->originalDatabase !== null) {
            MultiDB::setDb($this->originalDatabase);
        }

        parent::tearDown();
    }

    public function testIndexedLookupUpdatesAllGatewaysForTheAccount(): void
    {
        $firstGateway = $this->makeStripeGateway('acct_indexed');
        $secondGateway = $this->makeStripeGateway('acct_indexed');
        $unrelatedGateway = $this->makeStripeGateway('acct_unrelated');

        (new AccountUpdate($this->accountUpdatedEvent('acct_indexed')))->handle();

        $this->assertSame('Yes', $firstGateway->fresh()->settings->general->{'Payouts Enabled'});
        $this->assertSame('Yes', $secondGateway->fresh()->settings->general->{'Payouts Enabled'});
        $this->assertNull($unrelatedGateway->fresh()->settings);
    }

    public function testConfigLookupIsUsedAsFallbackAndBackfillsAccountId(): void
    {
        $gateway = $this->makeStripeGateway(null, 'acct_fallback');

        (new AccountUpdate($this->accountUpdatedEvent('acct_fallback')))->handle();

        $gateway = $gateway->fresh();

        $this->assertSame('acct_fallback', $gateway->gateway_account_id);
        $this->assertSame('Yes', $gateway->settings->general->{'Payouts Enabled'});
    }

    public function testIndexedMatchPreventsConfigFallbackLookup(): void
    {
        $indexedGateway = $this->makeStripeGateway('acct_shared');
        $fallbackGateway = $this->makeStripeGateway(null, 'acct_shared');

        (new AccountUpdate($this->accountUpdatedEvent('acct_shared')))->handle();

        $this->assertNotNull($indexedGateway->fresh()->settings);
        $this->assertNull($fallbackGateway->fresh()->settings);
        $this->assertNull($fallbackGateway->fresh()->gateway_account_id);
    }

    private function makeStripeGateway(?string $gatewayAccountId, ?string $configAccountId = null): CompanyGateway
    {
        $gateway = CompanyGatewayFactory::create($this->company->id, $this->user->id);
        $gateway->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $gateway->accepted_credit_cards = 0;
        $gateway->fees_and_limits = new \stdClass();
        $gateway->gateway_account_id = $gatewayAccountId;
        $gateway->setConfig(['account_id' => $configAccountId]);
        $gateway->save();

        return $gateway;
    }

    private function accountUpdatedEvent(string $accountId): \stdClass
    {
        $capabilities = new class {
            public function toArray(): array
            {
                return [];
            }
        };

        return (object) [
            'id' => 'evt_account_updated',
            'account' => $accountId,
            'type' => 'account.updated',
            'data' => (object) [
                'object' => (object) [
                    'id' => $accountId,
                    'payouts_enabled' => true,
                    'capabilities' => $capabilities,
                    'requirements' => (object) [
                        'currently_due' => [],
                        'past_due' => [],
                        'errors' => [],
                    ],
                ],
            ],
        ];
    }
}
