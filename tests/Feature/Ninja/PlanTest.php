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

namespace Tests\Feature\Ninja;

use App\Factory\SubscriptionFactory;
use App\Models\Account;
use App\Models\License;
use App\Models\RecurringInvoice;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class PlanTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testTrialFeatures()
    {
        config(['ninja.production' => true]);

        $this->assertFalse($this->account->hasFeature(Account::FEATURE_USERS));

        $this->account->trial_plan = 'enterprise';
        $this->account->trial_started = now();
        $this->account->trial_duration = 60 * 60 * 24 * 31;
        $this->account->save();

        $this->assertFalse($this->account->hasFeature(Account::FEATURE_USERS));

        $this->account->trial_plan = 'pro';
        $this->account->save();

        $this->assertFalse($this->account->hasFeature(Account::FEATURE_USERS));
        $this->assertTrue($this->account->hasFeature(Account::FEATURE_CUSTOM_URL));
    }

    public function testTrialFilter()
    {
        $plans = collect(['trial_pro', 'trial_enterprise', 'no_freebies']);

        $filtered_plans = $plans->filter(function ($plan) {
            return strpos($plan, 'trial_') !== false;
        });

        $this->assertEquals($filtered_plans->count(), 2);
    }

    public function testSubscriptionDateIncrement()
    {
        $subscription = SubscriptionFactory::create($this->company->id, $this->user->id);
        $subscription->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
        $subscription->save();

        $date = Carbon::parse('2020-01-01')->startOfDay();

        $next_date = $subscription->nextDateByInterval($date, RecurringInvoice::FREQUENCY_MONTHLY);

        $this->assertEquals($date->addMonthNoOverflow()->startOfDay(), $next_date->startOfDay());
    }

    public function testLicense()
    {
        $this->markTestSkipped();

        $license = new License();
        $license->license_key = "1234";
        $license->product_id = "3";
        $license->email = 'test@gmail.com';
        $license->is_claimed = 1;
        $license->save();

        $license->fresh();

        $response = $this->get("/claim_license?license_key=1234&product_id=3")
                    ->assertStatus(200);

        $response = $this->get("/claim_license?license_key=12345&product_id=3")
                    ->assertStatus(400);

    }
}
