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

namespace Tests\Unit\Jobs\Account;

use App\Jobs\Account\CreateAccount;
use App\Models\Account;
use Tests\TestCase;

class ApplyAttributionTest extends TestCase
{
    private function apply(array $attribution, ?Account $account = null): Account
    {
        $account ??= new Account();

        (new CreateAccount([], '127.0.0.1'))->applyAttribution($account, $attribution);

        return $account;
    }

    public function testAttributionIsStoredAndMirroredIntoLegacyColumns(): void
    {
        $attribution = [
            'utm_source'   => 'facebook',
            'utm_medium'   => 'cpc',
            'utm_campaign' => 'january_launch',
            'utm_term'     => 'crm',
            'utm_content'  => 'banner',
        ];

        $account = $this->apply($attribution);

        $this->assertSame($attribution, $account->attribution);
        $this->assertSame('facebook', $account->utm_source);
        $this->assertSame('cpc', $account->utm_medium);
        $this->assertSame('january_launch', $account->utm_campaign);
        $this->assertSame('crm', $account->utm_term);
        $this->assertSame('banner', $account->utm_content);
    }

    public function testNonMirroredKeysStayInAttributionOnly(): void
    {
        $account = $this->apply(['utm_creative' => 'video']);

        $this->assertSame(['utm_creative' => 'video'], $account->attribution);
        $this->assertNull($account->utm_source);
        $this->assertNull($account->utm_medium);
    }

    public function testFbcIsBuiltFromFbclidWhenMetaDidNotPassOne(): void
    {
        $account = $this->apply(['fbclid' => 'abc123']);

        $this->assertMatchesRegularExpression(
            '/^fb\.1\.\d{13}\.abc123$/',
            $account->attribution['fbc']
        );
    }

    public function testExistingFbcIsNotOverwritten(): void
    {
        $account = $this->apply([
            'fbclid' => 'abc123',
            'fbc'    => 'fb.1.1700000000000.abc123',
        ]);

        $this->assertSame('fb.1.1700000000000.abc123', $account->attribution['fbc']);
    }

    public function testFbcIsNotInventedWithoutAnFbclid(): void
    {
        $account = $this->apply(['utm_source' => 'facebook']);

        $this->assertArrayNotHasKey('fbc', $account->attribution);
    }

    public function testMirrorsDoNotOverwriteValuesMissingFromAttribution(): void
    {
        $account = $this->apply(['utm_medium' => 'cpc'], new Account(['utm_source' => 'google']));

        $this->assertSame('google', $account->utm_source);
        $this->assertSame('cpc', $account->utm_medium);
    }
}
