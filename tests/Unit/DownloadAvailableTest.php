<?php

namespace Tests\Unit;

use App\Events\Socket\DownloadAvailable;
use Illuminate\Support\Facades\Event;
use Tests\MockAccountData;
use Tests\TestCase;

class DownloadAvailableTest extends TestCase
{
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testNotifyBroadcastsDownloadAvailableWithFormattedMessage(): void
    {
        Event::fake([DownloadAvailable::class]);

        DownloadAvailable::notify($this->user, 'https://example.test/download.zip', '3 invoices');

        Event::assertDispatched(DownloadAvailable::class, function (DownloadAvailable $event) {
            return $event->url === 'https://example.test/download.zip'
                && str_contains($event->message, '3 invoices')
                && $event->user->is($this->user);
        });
    }
}
