<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026 Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Export;

use App\Http\Controllers\ExportController;
use App\Http\Requests\Export\StoreExportRequest;
use App\Jobs\Company\CompanyExport;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;

class ExportControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake([CompanyExport::class]);
        URL::forceRootUrl('https://internal.example.test');
        URL::forceScheme('https');
        config(['filesystems.protected_download_allow_unsigned' => false]);
    }

    public function testExportReturnsRelativeSignedDownloadUrl(): void
    {
        $url = $this->exportUrl();

        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);

        $proxied_url = str_replace(
            'https://internal.example.test',
            'http://public.example.test',
            $url,
        );

        $this->assertTrue(URL::hasValidSignature(Request::create($proxied_url), absolute: false));
    }

    public function testExportCanReturnUnsignedDownloadUrl(): void
    {
        config(['filesystems.protected_download_allow_unsigned' => true]);

        $url = $this->exportUrl();

        $this->assertNull(parse_url($url, PHP_URL_QUERY));
    }

    private function exportUrl(): string
    {
        $activities = Mockery::mock(HasMany::class);
        $activities->shouldReceive('count')->once()->andReturn(0);

        $company = Mockery::mock(Company::class);
        $company->shouldReceive('all_activities')->once()->andReturn($activities);

        $user = Mockery::mock(User::class);
        $user->shouldReceive('getCompany')->twice()->andReturn($company);

        $this->app['auth']->guard()->setUser($user);

        $response = app(ExportController::class)->index(Mockery::mock(StoreExportRequest::class));
        $url = $response->getData(true)['url'];

        $this->assertIsString($url);
        $this->assertStringStartsWith('https://internal.example.test/', $url);

        Queue::assertPushed(CompanyExport::class, function (CompanyExport $job) use ($company): bool {
            return $job->company === $company;
        });

        return $url;
    }
}
