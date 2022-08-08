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

use App\Jobs\Util\UploadFile;
use App\Models\Document;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Tests\MockAccountData;
use Tests\TestCase;

class ZeroDecimalTest extends TestCase
{
    public array $currencies = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    protected function setUp() :void
    {
    }

    public function testCurrencyHit()
    {
        $this->assertTrue(in_array('KRW', $this->currencies));
    }

    public function testCurrencyMiss()
    {
        $this->assertFalse(in_array('USD', $this->currencies));
    }

    public function testCurrencyNotexist()
    {
        $this->assertFalse(in_array('USDddd', $this->currencies));
    }
}
