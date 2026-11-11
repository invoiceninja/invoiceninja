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

namespace Tests\Feature\Import\CSV;

use App\Import\Providers\Csv;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

class ClientGroupImportTest extends TestCase
{
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        config(['database.default' => config('ninja.db.default')]);

        $this->makeTestData();
    }

    public function testSameClientNameGroupsContactsTogether(): void
    {
        $acmeOne = ['client.name' => 'Acme', 'contact.email' => 'one@example.com'];
        $acmeTwo = ['client.name' => 'Acme', 'contact.email' => 'two@example.com'];

        $importer = $this->importer();
        $grouped = $importer->groupClients([
            1 => $acmeOne,
            2 => $acmeTwo,
        ], 'client.name');

        $this->assertSame([
            'Acme' => [
                1 => $acmeOne,
                2 => $acmeTwo,
            ],
        ], $grouped);
        $this->assertSame([], $importer->getErrors());
    }

    public function testBlankClientNameIsItsOwnGroupAndDoesNotError(): void
    {
        $blank = ['client.name' => '', 'contact.email' => 'blank@example.com'];

        $importer = $this->importer();
        $grouped = $importer->groupClients([
            1 => $blank,
        ], 'client.name');

        $this->assertSame([
            1 => [
                1 => $blank,
            ],
        ], $grouped);
        $this->assertSame([], $importer->getErrors());
    }

    public function testTwoBlankClientNamesRemainSeparateGroups(): void
    {
        $first = ['client.name' => '', 'contact.email' => 'first@example.com'];
        $second = ['client.name' => '', 'contact.email' => 'second@example.com'];

        $importer = $this->importer();
        $grouped = $importer->groupClients([
            1 => $first,
            2 => $second,
        ], 'client.name');

        $this->assertSame([
            1 => [
                1 => $first,
            ],
            2 => [
                2 => $second,
            ],
        ], $grouped);
        $this->assertSame([], $importer->getErrors());
    }

    public function testBlankNameDoesNotJoinANamedClientGroup(): void
    {
        $acme = ['client.name' => 'Acme', 'contact.email' => 'acme@example.com'];
        $blank = ['client.name' => '', 'contact.email' => 'blank@example.com'];

        $importer = $this->importer();
        $grouped = $importer->groupClients([
            1 => $acme,
            2 => $blank,
        ], 'client.name');

        $this->assertSame([
            'Acme' => [
                1 => $acme,
            ],
            2 => [
                2 => $blank,
            ],
        ], $grouped);
        $this->assertSame([], $importer->getErrors());
    }

    public function testUnmappedClientNameKeepsEachRowAsItsOwnGroup(): void
    {
        $first = ['contact.email' => 'first@example.com'];
        $second = ['contact.email' => 'second@example.com'];

        $importer = $this->importer();
        $grouped = $importer->groupClients([
            1 => $first,
            2 => $second,
        ], 'client.name');

        $this->assertSame([
            1 => [
                1 => $first,
            ],
            2 => [
                2 => $second,
            ],
        ], $grouped);
        $this->assertSame([], $importer->getErrors());
    }

    private function importer(): Csv
    {
        return new Csv([
            'hash' => 'client-group-import-test',
            'column_map' => [
                'client' => ['mapping' => [0 => 'client.name']],
            ],
            'skip_header' => true,
            'import_type' => 'csv',
        ], $this->company);
    }
}
