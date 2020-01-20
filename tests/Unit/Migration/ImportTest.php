<?php

namespace Tests\Unit\Migration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testImportClassExists(): void
    {
        $status = class_exists('App\Jobs\Util\Import');

        $this->assertTrue($status);
    }
}
