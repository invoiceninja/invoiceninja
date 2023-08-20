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

namespace Tests\Unit\Migration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public $migration_array;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $migration_file = base_path().'/tests/Unit/Migration/migration.json';

        $this->migration_array = json_decode(file_get_contents($migration_file), 1);
    }

    public function testImportClassExists()
    {
        $status = class_exists(\App\Jobs\Util\Import::class);

        $this->assertTrue($status);
    }

}
