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

namespace Tests\Unit;

use App\Events\Socket\DownloadAvailable;
use App\Jobs\Util\UnlinkFile;
use App\Services\Download\TemporaryDownloadPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use RuntimeException;
use Tests\MockAccountData;
use Tests\TestCase;

class TemporaryDownloadPublisherTest extends TestCase
{
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.default' => 'public',
            'filesystems.protected_download_disk' => 'protected-downloads',
        ]);

        Storage::fake('public');
        Storage::fake('protected-downloads');
        Cache::flush();
        Event::fake([DownloadAvailable::class]);
        Queue::fake([UnlinkFile::class]);
        URL::forceRootUrl('https://example.test');

        $this->makeTestData();
    }

    public function testPublishStoresStructuredRecordOnPrivateDisk(): void
    {
        $expires_at = now()->addHour();
        $storage_path = $this->company->file_path() . 'downloads/report.zip';

        $result = app(TemporaryDownloadPublisher::class)->publish(
            contents: 'archive contents',
            storage_path: $storage_path,
            download_name: 'report.zip',
            expires_at: $expires_at,
            user: $this->user,
        );

        Storage::disk('protected-downloads')->assertExists($storage_path);
        Storage::disk('public')->assertMissing($storage_path);

        $this->assertSame([
            'disk' => 'protected-downloads',
            'path' => $storage_path,
            'download_name' => 'report.zip',
            'expires_at' => $expires_at->timestamp,
        ], Cache::get($result->hash));
        $this->assertTrue(URL::hasValidSignature(Request::create($result->url)));

        Queue::assertPushed(UnlinkFile::class, function (UnlinkFile $job) use ($expires_at): bool {
            return $job->delay?->equalTo($expires_at) === true;
        });

        Event::assertDispatched(DownloadAvailable::class, function (DownloadAvailable $event) use ($result): bool {
            return $event->url === $result->url && $event->user->is($this->user);
        });
    }

    public function testPublishStandardizesFailures(): void
    {
        config(['filesystems.protected_download_disk' => 'missing-disk']);

        try {
            app(TemporaryDownloadPublisher::class)->publish(
                contents: 'archive contents',
                storage_path: 'downloads/report.zip',
                download_name: 'report.zip',
                expires_at: now()->addHour(),
            );

            $this->fail('Expected publication to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to publish protected download.', $exception->getMessage());
            $this->assertSame(500, $exception->getCode());
            $this->assertInstanceOf(InvalidArgumentException::class, $exception->getPrevious());
        }
    }
}
