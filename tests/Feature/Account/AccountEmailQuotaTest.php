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

namespace Tests\Feature\Account;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Http\Livewire\CreditsTable;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Credit;
use App\Models\User;
use App\Utils\Traits\AppSetup;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\MockAccountData;
use Tests\TestCase;

class AccountEmailQuotaTest extends TestCase
{
    use DatabaseTransactions;
    use AppSetup;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
        $this->buildCache(true);
        $this->makeTestData();
    }

    public function testQuotaValidRule()
    {
        Cache::increment($this->account->key);

        $this->assertFalse($this->account->emailQuotaExceeded());
    }

    public function testQuotaInValidRule()
    {
        Cache::increment($this->account->key, 3000);

        $this->assertTrue($this->account->emailQuotaExceeded());
    }
}
