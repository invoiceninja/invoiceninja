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
 * What `/` serves: the hosted landing page, the Flutter web app unpacked from
 * the Flutter app's release (public/flutter, resources/views/flutter), React,
 * or, on a git checkout that has neither build, admin-portal's app.
 *
 * Both new views arrive with the release tarball and are not in the repo, so
 * these register stand-ins from a temporary folder, and point the public path
 * at a temporary install.
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
        File::put($this->dir.'/views/landing/apps.blade.php', 'LANDING PAGE');
        File::put($this->dir.'/views/flutter/index.blade.php', 'FLUTTER WEB base={{ $base_href }} hosted={{ $is_hosted ? 1 : 0 }} microsoft={{ $microsoft_client_id }}');
        View::addLocation($this->dir.'/views');

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
     * A release install: the Flutter web app and/or React's entry bundle.
     */
    private function install(bool $flutter, bool $react): void
    {
        File::ensureDirectoryExists($this->dir.'/public/flutter');

        if ($flutter) {
            File::put($this->dir.'/public/flutter/flutter_bootstrap.js', '');
        }

        if ($react) {
            File::put($this->dir.'/public/bundle.abc123.js', '');
        }

        $this->app->usePublicPath($this->dir.'/public');
    }

    public function testHostedServesTheLandingPage()
    {
        config(['ninja.environment' => 'hosted', 'ninja.hosted_landing' => true]);

        $this->get('/')
            ->assertStatus(200)
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
        File::delete($this->dir.'/views/landing/apps.blade.php');

        $this->get('/')
            ->assertStatus(200)
            ->assertDontSee('LANDING PAGE')
            ->assertSee('main.dart.js', false);
    }

    public function testHostedNeverServesTheFlutterWebAppItself()
    {
        // Hosted serves it from its own host; a second copy here would be a second origin.
        config(['ninja.environment' => 'hosted']);
        $this->install(flutter: true, react: true);

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
        $this->install(flutter: true, react: true);

        $this->get('/')
            ->assertStatus(200)
            ->assertSee('FLUTTER WEB base=/ninja/flutter/ hosted=0 microsoft=ms-client-id', false);
    }

    public function testSelfHostServesReactWhenOnlyReactIsBuilt()
    {
        // A release built before the Flutter app shipped its web build.
        config(['ninja.environment' => 'selfhost']);
        $this->install(flutter: false, react: true);

        $this->get('/')
            ->assertStatus(200)
            ->assertDontSee('FLUTTER WEB')
            ->assertDontSee('main.foss.dart.js', false)
            ->assertSee('<div id="root"></div>', false);
    }

    public function testAGitCheckoutStillServesTheOldApp()
    {
        // Neither build is in the repo; React's view alone would be a blank page.
        config(['ninja.environment' => 'selfhost']);
        $this->install(flutter: false, react: false);

        $this->get('/')
            ->assertStatus(200)
            ->assertDontSee('FLUTTER WEB')
            ->assertSee('main.foss.dart.js', false);
    }

    public function testReactAccountsStayOnReact()
    {
        config(['ninja.environment' => 'selfhost']);
        $this->install(flutter: true, react: true);
        Account::query()->update(['set_react_as_default_ap' => true]);

        $this->get('/')
            ->assertDontSee('FLUTTER WEB')
            ->assertSee('<div id="root"></div>', false);
    }

    public function testTheBuildQueryNoLongerPicksTheOldAppOnSelfHost()
    {
        config(['ninja.environment' => 'selfhost']);
        $this->install(flutter: true, react: true);

        $this->get('/?build=legacy')
            ->assertSee('FLUTTER WEB', false)
            ->assertCookieMissing('flutter_web_build');
    }
}
