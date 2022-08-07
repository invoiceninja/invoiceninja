<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Models\User;
use App\Utils\Traits\AppSetup;
use Tests\MockUnitData;
use Tests\TestCase;

/**
 * @test
 */
class CreditBalanceTest extends TestCase
{
    use MockUnitData;
    use AppSetup;

    protected function setUp() :void
    {
        parent::setUp();

        Credit::all()->each(function ($credit) {
            $credit->forceDelete();
        });

        $this->makeTestData();

        $this->buildCache(true);
    }

    public function testCreditBalance()
    {
        $credit = Credit::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'balance' => 10,
            'number' => 'testing-number-01',
            'status_id' => Credit::STATUS_SENT,
        ]);

        $this->assertEquals($this->client->service()->getCreditBalance(), 10);
    }

    public function testExpiredCreditBalance()
    {
        $credit = Credit::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'balance' => 10,
            'due_date' => now()->addDays(5),
            'number' => 'testing-number-02',
            'status_id' => Credit::STATUS_SENT,
        ]);

        $this->assertEquals($this->client->service()->getCreditBalance(), 0);
    }
}
