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

namespace Tests\Feature\Export;

use App\Jobs\Company\CompanyExport;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\MockAccountData;
use Tests\TestCase;
use ZipArchive;

/**
 *
 */
class ExportCompanyTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        // $this->faker = \Faker\Factory::create();

        $this->makeTestData();

        $this->withoutExceptionHandling();

        if (!config('ninja.testvars.stripe')) {
            $this->markTestSkipped('Cannot write to TMP - skipping');
        }
    }

    public function testCompanyExport(): void
    {
        Storage::fake(config('filesystems.default'));

        $this->project->hash = 'project-hash';
        $this->project->meta = json_encode(['external_id' => 'project-meta'], JSON_THROW_ON_ERROR);
        $this->project->save();

        $hash = 'company-export-test';
        Cache::put($hash, 'https://example.test/download');

        $res = (new CompanyExport($this->company, $this->company->users->first(), $hash))->handle();

        $this->assertTrue($res);

        $backup_path = Cache::get($hash);
        $zip = new ZipArchive();

        $this->assertTrue($zip->open(Storage::disk(config('filesystems.default'))->path($backup_path)));

        $backup = $zip->getFromName('backup.json');
        $zip->close();

        $this->assertIsString($backup);

        $export = json_decode($backup, true, flags: JSON_THROW_ON_ERROR);
        $project = collect($export['projects'])->firstWhere('id', $this->project->id);

        $this->assertNotNull($project);
        $this->assertArrayNotHasKey('hash', $project);
        $this->assertArrayNotHasKey('meta', $project);
    }
}
