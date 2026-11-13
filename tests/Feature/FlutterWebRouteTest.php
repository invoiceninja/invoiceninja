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

namespace Tests\Feature;

use App\Models\Account;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * What `/` serves: the hosted landing page, the new Flutter web app unpacked
 * from its release (public/flutter, resources/views/flutter), or, where that
 * isn't installed (a git checkout, a release that couldn't fetch it),
 * admin-portal's app.
 *
 * Both pages arrive with the release tarball and are not in the repo, so these
 * put stand-ins in a temporary view folder, and point the public path at a
 * temporary install. They are plain HTML the server fills or serves as a file:
 * nothing from the release is compiled.
 */
class FlutterWebRouteTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->dir = sys_get_temp_dir().'/flutter-web-route-'.uniqid();
        File::ensureDirectoryExists($this->dir.'/views/landing');
        File::ensureDirectoryExists($this->dir.'/views/flutter');
        File::put($this->dir.'/views/landing/index.html', 'LANDING PAGE');
        File::put($this->dir.'/views/flutter/index.html', 'FLUTTER WEB base=__IN_BASE_HREF__ hosted=__IN_HOSTED__ microsoft=__IN_MICROSOFT_CLIENT_ID__ rc=__IN_RC__ new=[__IN_SOMETHING_NEWER__] {{ 6 * 7 }} @php echo 42; @endphp');
        View::prependLocation($this->dir.'/views');

        if (Account::count() === 0) {
            Account::factory()->create();
        }

        Account::query()->update(['set_react_as_default_ap' => false]);

        // Test requests go to APP_URL's host; hosted bounces any other host to app_url.
        config([
            'ninja.app_url' => rtrim(url('/'), '/'),
            'ninja.require_https' => false,
            'ninja.hosted_landing' => false,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    /**
     * A release install, with or without the new Flutter web app.
     */
    private function install(bool $flutter): void
    {
        File::ensureDirectoryExists($this->dir.'/public/flutter');

        if ($flutter) {
            File::put($this->dir.'/public/flutter/flutter_bootstrap.js', '');
        }

        $this->app->usePublicPath($this->dir.'/public');
    }

    public function testHostedServesTheLandingPage()
    {
        config(['ninja.environment' => 'hosted', 'ninja.hosted_landing' => true]);

        $this->get('/')
            ->assertStatus(200)
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertSee('LANDING PAGE');
    }

    public function testTheLandingPageKeepsTheQueryForTheAppTheVisitorPicks()
    {
        // Rendered ahead of the ?signup / ?login clean-up redirect, which drops the query.
        config(['ninja.environment' => 'hosted', 'ninja.hosted_landing' => true]);

        $this->get('/?signup=true&rc=ABC123')
            ->assertStatus(200)
            ->assertSee('LANDING PAGE');
    }

    public function testHostedWithoutTheLandingPageKeepsTheOldApp()
    {
        config(['ninja.environment' => 'hosted']);

        $this->get('/')
            ->assertStatus(200)
            ->assertDontSee('LANDING PAGE')
            ->assertSee('main.dart.js', false);
    }

    public function testAnyBuildSkipsTheLandingPage()
    {
        // The existing test builds (next, last, profile, wasm) stay reachable until the old app is removed.
        config(['ninja.environment' => 'hosted', 'ninja.hosted_landing' => true]);

        $this->get('/?build=next')
            ->assertStatus(200)
            ->assertDontSee('LANDING PAGE')
            ->assertSee('main.next.dart.js', false);
    }

    public function testHostedFallsBackToTheOldAppWhenTheLandingPageIsNotInstalled()
    {
        config(['ninja.environment' => 'hosted', 'ninja.hosted_landing' => true]);
        File::delete($this->dir.'/views/landing/index.html');

        $this->get('/')
            ->assertStatus(200)
            ->assertDontSee('LANDING PAGE')
            ->assertSee('main.dart.js', false);
    }

    public function testHostedNeverServesTheFlutterWebAppItself()
    {
        // Hosted serves it from its own host; a second copy here would be a second origin.
        config(['ninja.environment' => 'hosted']);
        $this->install(flutter: true);

        $this->get('/')
            ->assertStatus(200)
            ->assertDontSee('FLUTTER WEB')
            ->assertSee('main.dart.js', false);
    }

    public function testSelfHostServesTheFlutterWebApp()
    {
        config([
            'ninja.environment' => 'selfhost',
            'ninja.app_url' => rtrim(url('/'), '/').'/ninja',
            'services.microsoft.client_id' => 'ms-client-id',
        ]);
        $this->install(flutter: true);

        $this->get('/')
            ->assertStatus(200)
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertSee('FLUTTER WEB base=/ninja/flutter/ hosted=0 microsoft=ms-client-id', false);
    }

    public function testTheBootPageIsFilledWithEscapedValuesAndNeverCompiled()
    {
        config(['ninja.environment' => 'selfhost']);
        $this->install(flutter: true);

        $this->get('/?rc=<b>"x"</b>')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('rc=&lt;b&gt;&quot;x&quot;&lt;/b&gt;', false)
            // A token this server doesn't know yet is left empty.
            ->assertSee('new=[]', false)
            // Blade in the page is just text.
            ->assertSee('{{ 6 * 7 }} @php echo 42; @endphp', false)
            ->assertDontSee('42 ', false);
    }

    public function testSelfHostKeepsTheOldAppWithoutTheNewOne()
    {
        // A git checkout, or a release that couldn't fetch the Flutter web build.
        config(['ninja.environment' => 'selfhost']);
        $this->install(flutter: false);

        $this->get('/')
            ->assertStatus(200)
            ->assertDontSee('FLUTTER WEB')
            ->assertSee('main.foss.dart.js', false);
    }

    public function testTheNewAppNeedsItsViewToo()
    {
        // public/flutter without resources/views/flutter: a half-extracted release.
        config(['ninja.environment' => 'selfhost']);
        $this->install(flutter: true);
        File::delete($this->dir.'/views/flutter/index.html');

        $this->get('/')
            ->assertStatus(200)
            ->assertSee('main.foss.dart.js', false);
    }

    public function testReactAccountsStayOnReact()
    {
        config(['ninja.environment' => 'selfhost']);
        $this->install(flutter: true);
        Account::query()->update(['set_react_as_default_ap' => true]);

        $this->get('/')
            ->assertDontSee('FLUTTER WEB')
            ->assertSee('<div id="root"></div>', false);
    }

    public function testTheOldAppsTestBuildsAreHostedOnly()
    {
        // ?build=next etc. pick an old-app build; a self-host with the new app has none.
        config(['ninja.environment' => 'selfhost']);
        $this->install(flutter: true);

        $this->get('/?build=next')
            ->assertSee('FLUTTER WEB', false)
            ->assertDontSee('main.next.dart.js', false);
    }
}
