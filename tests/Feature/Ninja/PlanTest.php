<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Feature\Ninja;

use App\Models\Account;
use App\Utils\Traits\MakesHash;
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

    public function setUp() :void
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
        $this->account->trial_duration = 60*60*24*31;
        $this->account->save();
    
        $this->assertFalse($this->account->hasFeature(Account::FEATURE_USERS));

        $this->account->trial_plan = 'pro';
        $this->account->save();

        $this->assertFalse($this->account->hasFeature(Account::FEATURE_USERS));
        $this->assertTrue($this->account->hasFeature(Account::FEATURE_CUSTOM_URL));

    }


}
