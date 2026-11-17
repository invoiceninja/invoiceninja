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

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Transformers\AccountTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AccountAttributionExposureTest extends TestCase
{
    use DatabaseTransactions;

    public function testAttributionIsNotExposedByTheAccountTransformer(): void
    {
        $account = Account::factory()->create();
        $account->attribution = ['utm_source' => 'facebook', 'utm_creative' => 'video'];
        $account->save();

        $payload = (new AccountTransformer())->transform($account->fresh());

        $this->assertArrayNotHasKey('attribution', $payload);
        $this->assertStringNotContainsString('utm_creative', json_encode($payload));
    }
}
