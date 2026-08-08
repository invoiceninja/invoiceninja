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

namespace Tests\Unit\Company;

use App\Jobs\Company\CompanyImport;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\JsonMachine;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;
use ZipArchive;

/**
 * Characterization tests for CompanyImport::getObject().
 */
class CompanyImportGetObjectTest extends TestCase
{
    use DatabaseTransactions;

    private string $minimal_backup_path;

    private string $full_backup_path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->minimal_backup_path = base_path('tests/Fixtures/Import/minimal_backup.json');
        $this->skipUnlessImportFixtureExists($this->minimal_backup_path, 'minimal backup JSON');

        $this->full_backup_path = $this->extractFullBackupJson();
    }

    protected function tearDown(): void
    {
        if (isset($this->full_backup_path) && file_exists($this->full_backup_path)) {
            unlink($this->full_backup_path);
        }

        parent::tearDown();
    }

    public function testGetObjectAlwaysReturnsArrayForListKeys(): void
    {
        $import = $this->makeImport($this->minimal_backup_path);

        $users = $this->invokeGetObject($import, 'users');

        $this->assertIsArray($users);
        $this->assertCount(2, $users);
    }

    public function testGetObjectAlwaysReturnsArrayForRecordKeys(): void
    {
        $import = $this->makeImport($this->minimal_backup_path);

        $company = $this->invokeGetObject($import, 'company');

        $this->assertIsArray($company);
        $this->assertArrayHasKey('company_key', $company);
    }

    public function testReturnedIteratorRemainsIterableAfterInternalIteratorToArray(): void
    {
        $json = JsonMachine::fromFile($this->minimal_backup_path, '/users', new ExtJsonDecoder());

        $iterator_array = iterator_to_array($json);

        $this->assertCount(2, $iterator_array);

        $count = 0;
        $hashed_ids = [];

        foreach ((object) $json as $user) {
            $count++;
            $hashed_ids[] = $user->hashed_id;
        }

        $this->assertSame(2, $count);
        $this->assertSame(['VolejRejNm', 'Wpmbk5ezJn'], $hashed_ids);
    }

    public function testObjectCastLoopMatchesDirectArrayLoopForListKeys(): void
    {
        $import = $this->makeImport($this->minimal_backup_path);

        foreach (['users', 'expenses', 'tax_rates'] as $key) {
            $with_object_cast = $this->collectUsingObjectCastPattern($import, $key);
            $direct_array = $this->collectUsingDirectArrayPattern($import, $key);

            $this->assertCount(count($with_object_cast), $direct_array, "Item count mismatch for [{$key}]");
            $this->assertSame(
                $this->extractComparableValues($with_object_cast),
                $this->extractComparableValues($direct_array),
                "Item payload mismatch for [{$key}]"
            );
        }
    }

    public function testObjectCastLoopMatchesDirectArrayLoopAgainstFullBackup(): void
    {
        $import = $this->makeImport($this->full_backup_path);

        foreach (['users', 'expenses', 'tax_rates', 'designs', 'payments'] as $key) {
            $with_object_cast = $this->collectUsingObjectCastPattern($import, $key);
            $direct_array = $this->collectUsingDirectArrayPattern($import, $key);

            $this->assertSame(count($with_object_cast), count($direct_array), "Item count mismatch for [{$key}] in full backup");
            $this->assertSame(
                $this->extractComparableValues($with_object_cast),
                $this->extractComparableValues($direct_array),
                "Item payload mismatch for [{$key}] in full backup"
            );
        }
    }

    public function testGetObjectRecordReturnsObjectForSingletonKeys(): void
    {
        $import = $this->makeImport($this->minimal_backup_path);

        $this->assertIsObject($this->invokeGetObjectRecord($import, 'company'));
        $this->assertIsObject($this->invokeGetObjectRecord($import, 'app_version'));
    }

    public function testCompanyRecordSupportsObjectPropertyAccess(): void
    {
        $import = $this->makeImport($this->minimal_backup_path);

        $company = $this->invokeGetObjectRecord($import, 'company');

        $this->assertTrue(property_exists($company, 'company_key'));
        $this->assertSame(
            'f7sy6y9gtc2pig0yye9gy4tvtsizmtzbwmyx3fgaszyvhn4bfnclgvrljfe580wt',
            $company->company_key
        );
        $this->assertTrue(property_exists($company, 'settings'));
    }

    public function testAppVersionSupportsObjectPropertyAccess(): void
    {
        $import = $this->makeImport($this->minimal_backup_path);

        $data = $this->invokeGetObjectRecord($import, 'app_version');

        $this->assertTrue(property_exists($data, 'app_version'));
        $this->assertSame('5.1.65', $data->app_version);
    }

    public function testMissingKeyReturnsEmptyArray(): void
    {
        $import = $this->makeImport($this->minimal_backup_path);

        $missing = $this->invokeGetObject($import, 'does_not_exist');

        $this->assertIsArray($missing);
        $this->assertSame([], $missing);
    }

    public function testListItemsDecodeToObjectsForPropertyAccess(): void
    {
        $import = $this->makeImport($this->minimal_backup_path);

        foreach ($this->invokeGetObject($import, 'users') as $user) {
            $this->assertIsObject($user);
            $this->assertTrue(property_exists($user, 'hashed_id'));
            $this->assertTrue(property_exists($user, 'email'));
        }
    }

    public function testObjectCastOnArrayListStillIteratesForImportPattern(): void
    {
        $users = $this->invokeGetObject($this->makeImport($this->minimal_backup_path), 'users');

        $count = 0;

        foreach ((object) $users as $user) {
            $count++;
            $this->assertIsObject($user);
        }

        $this->assertSame(2, $count);
    }

    private function makeImport(string $backup_path): CompanyImport
    {
        $account = Account::factory()->create();
        $company = Company::factory()->create(['account_id' => $account->id]);
        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => 'company-import-get-object-' . uniqid('', true) . '@gmail.com',
        ]);

        $import = new CompanyImport($company, $user, 'unused-location', []);

        $file_path = new ReflectionProperty(CompanyImport::class, 'file_path');
        $file_path->setAccessible(true);
        $file_path->setValue($import, $backup_path);

        return $import;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function invokeGetObject(CompanyImport $import, string $key): array
    {
        $method = new ReflectionMethod(CompanyImport::class, 'getObject');
        $method->setAccessible(true);

        return $method->invoke($import, $key);
    }

    private function invokeGetObjectRecord(CompanyImport $import, string $key): object
    {
        $method = new ReflectionMethod(CompanyImport::class, 'getObjectRecord');
        $method->setAccessible(true);

        return $method->invoke($import, $key);
    }

    /**
     * @return list<object>
     */
    private function collectUsingObjectCastPattern(CompanyImport $import, string $key): array
    {
        $items = [];

        foreach ((object) $this->invokeGetObject($import, $key) as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return list<object>
     */
    private function collectUsingDirectArrayPattern(CompanyImport $import, string $key): array
    {
        $items = [];

        foreach ($this->invokeGetObject($import, $key) as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param  list<object>  $items
     * @return list<array<string, mixed>>
     */
    private function extractComparableValues(array $items): array
    {
        return array_map(static function ($item): array {
            return json_decode(json_encode($item), true);
        }, $items);
    }

    private function skipUnlessImportFixtureExists(string $path, string $fixture): void
    {
        if (! file_exists($path)) {
            $this->markTestSkipped("CompanyImportGetObjectTest requires the {$fixture} fixture at [{$path}].");
        }
    }

    private function extractFullBackupJson(): string
    {
        $zip_path = base_path('tests/Feature/Import/backup.zip');
        $target = sys_get_temp_dir() . '/company_import_get_object_' . uniqid('', true) . '.json';

        $this->skipUnlessImportFixtureExists($zip_path, 'full backup zip');

        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('CompanyImportGetObjectTest requires the ZipArchive extension to extract the full backup fixture.');
        }

        $zip = new ZipArchive();
        $result = $zip->open($zip_path);

        if ($result !== true) {
            $this->markTestSkipped("CompanyImportGetObjectTest requires a readable full backup zip fixture at [{$zip_path}].");
        }

        $contents = $zip->getFromName('backup/backup.json');
        $zip->close();

        if ($contents === false) {
            $this->markTestSkipped('CompanyImportGetObjectTest requires backup/backup.json inside the full backup zip fixture.');
        }

        $this->assertNotFalse(file_put_contents($target, $contents), 'Expected extracted backup fixture to write to the temp directory.');

        return $target;
    }
}
