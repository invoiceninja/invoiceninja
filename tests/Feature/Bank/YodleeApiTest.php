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

namespace Tests\Feature\Bank;

use App\Helpers\Bank\Yodlee\Yodlee;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class YodleeApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testAccessTokenGeneration()
    {

        $yodlee = new Yodlee(true);

        $access_token = $yodlee->getAccessToken();

        nlog($access_token);

        $this->assertNotNull($access_token);
    }

}
