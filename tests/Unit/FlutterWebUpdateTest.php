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

use App\Utils\FlutterWebUpdate;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Self-update's swap of the Flutter web app (SelfUpdateController::update), on a temporary
 * install and real tar archives: the new copy is staged and checked before anything changes,
 * and the previous one is kept until the new one is in place.
 */
class FlutterWebUpdateTest extends TestCase
{
    private string $dir;

    private string $base;

    private string $staging;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/flutter-web-update-'.uniqid();
        $this->base = $this->dir.'/install';
        $this->staging = $this->dir.'/staging';

        // The installed copy, with a file the new release no longer has.
        $this->writeFiles($this->base, [
            'public/flutter/flutter_bootstrap.js' => 'old bootstrap',
            'public/flutter/old_only.js' => 'old',
            'resources/views/flutter/index.html' => 'old page',
            'app/Kept.php' => 'old app code',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function testTheNewCopyReplacesTheOldOneWhole()
    {
        $release = $this->release('full', [
            'public/flutter/flutter_bootstrap.js' => 'new bootstrap',
            'public/flutter/assets/new.png' => 'new',
            'resources/views/flutter/index.html' => 'new page',
            'app/Kept.php' => 'new app code',
        ]);
        $update = new FlutterWebUpdate($this->base, $this->staging);

        $this->assertTrue($update->stage($release));
        $update->setAside();
        $release->extractTo($this->base, null, true);
        $this->assertTrue($update->install());

        $this->assertSame('new bootstrap', file_get_contents($this->base.'/public/flutter/flutter_bootstrap.js'));
        $this->assertSame('new page', file_get_contents($this->base.'/resources/views/flutter/index.html'));
        $this->assertFileExists($this->base.'/public/flutter/assets/new.png');
        $this->assertFileDoesNotExist($this->base.'/public/flutter/old_only.js');
        $this->assertDirectoryDoesNotExist($this->base.'/public/flutter.previous');
        $this->assertDirectoryDoesNotExist($this->base.'/resources/views/flutter.previous');
        $this->assertDirectoryDoesNotExist($this->staging);
    }

    public function testAnIncompleteCopyIsRefusedBeforeAnythingChanges()
    {
        $release = $this->release('incomplete', [
            'public/flutter/flutter_bootstrap.js' => 'new bootstrap',
            'app/Kept.php' => 'new app code',
        ]);
        $update = new FlutterWebUpdate($this->base, $this->staging);

        try {
            $update->stage($release);
            $this->fail('An incomplete copy was staged.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('resources/views/flutter', $e->getMessage());
        }

        $this->assertInstallIsTheOldOne();
        $this->assertDirectoryDoesNotExist($this->staging);
    }

    public function testACopyWithoutItsBootPageIsRefusedBeforeAnythingChanges()
    {
        $release = $this->release('no-boot-page', [
            'public/flutter/flutter_bootstrap.js' => 'new bootstrap',
            'resources/views/flutter/other.html' => 'not the boot page',
        ]);
        $update = new FlutterWebUpdate($this->base, $this->staging);

        try {
            $update->stage($release);
            $this->fail('A copy without its boot page was staged.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('has no resources/views/flutter/index.html', $e->getMessage());
        }

        $this->assertInstallIsTheOldOne();
        $this->assertDirectoryDoesNotExist($this->staging);
    }

    public function testAFailedExtractionRestoresThePreviousCopy()
    {
        $release = $this->release('full', [
            'public/flutter/flutter_bootstrap.js' => 'new bootstrap',
            'resources/views/flutter/index.html' => 'new page',
        ]);
        $update = new FlutterWebUpdate($this->base, $this->staging);

        $update->stage($release);
        $update->setAside();
        // Half an extraction: a new file lands where the old copy was.
        $this->writeFiles($this->base, ['public/flutter/flutter_bootstrap.js' => 'half-written']);
        $update->restore();

        $this->assertInstallIsTheOldOne();
        $this->assertDirectoryDoesNotExist($this->base.'/public/flutter.previous');
        $this->assertDirectoryDoesNotExist($this->staging);
    }

    public function testAFailedSwapRestoresThePreviousCopy()
    {
        $release = $this->release('full', [
            'public/flutter/flutter_bootstrap.js' => 'new bootstrap',
            'resources/views/flutter/index.html' => 'new page',
        ]);
        $update = new FlutterWebUpdate($this->base, $this->staging);

        $update->stage($release);
        $update->setAside();
        $release->extractTo($this->base, null, true);
        // The staged copy is gone by the time it should move into place.
        File::deleteDirectory($this->staging);

        $this->assertFalse($update->install());
        $this->assertInstallIsTheOldOne();
    }

    public function testAReleaseWithoutTheAppRemovesTheOldOne()
    {
        // Self-hosted / then falls back to the app every release carries, not an old build.
        $release = $this->release('without', ['app/Kept.php' => 'new app code']);
        $update = new FlutterWebUpdate($this->base, $this->staging);

        $this->assertFalse($update->stage($release));
        $update->setAside();
        $release->extractTo($this->base, null, true);
        $this->assertTrue($update->install());

        $this->assertDirectoryDoesNotExist($this->base.'/public/flutter');
        $this->assertDirectoryDoesNotExist($this->base.'/resources/views/flutter');
        $this->assertDirectoryDoesNotExist($this->base.'/public/flutter.previous');
        $this->assertSame('new app code', file_get_contents($this->base.'/app/Kept.php'));
    }

    public function testAFirstInstallThatFailsLeavesNoHalfCopy()
    {
        File::deleteDirectory($this->base.'/public/flutter');
        File::deleteDirectory($this->base.'/resources/views/flutter');
        $release = $this->release('full', [
            'public/flutter/flutter_bootstrap.js' => 'new bootstrap',
            'resources/views/flutter/index.html' => 'new page',
        ]);
        $update = new FlutterWebUpdate($this->base, $this->staging);

        $update->stage($release);
        $update->setAside();
        $this->writeFiles($this->base, ['public/flutter/flutter_bootstrap.js' => 'half-written']);
        $update->restore();

        $this->assertDirectoryDoesNotExist($this->base.'/public/flutter');
        $this->assertDirectoryDoesNotExist($this->base.'/resources/views/flutter');
    }

    private function assertInstallIsTheOldOne(): void
    {
        $this->assertSame('old bootstrap', file_get_contents($this->base.'/public/flutter/flutter_bootstrap.js'));
        $this->assertSame('old', file_get_contents($this->base.'/public/flutter/old_only.js'));
        $this->assertSame('old page', file_get_contents($this->base.'/resources/views/flutter/index.html'));
    }

    /** @param array<string, string> $files */
    private function writeFiles(string $root, array $files): void
    {
        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname($root.'/'.$path));
            File::put($root.'/'.$path, $contents);
        }
    }

    /** @param array<string, string> $files */
    private function release(string $name, array $files): \PharData
    {
        $source = $this->dir.'/'.$name.'-source';
        $this->writeFiles($source, $files);

        $release = new \PharData($this->dir.'/'.$name.'.tar');
        $release->buildFromDirectory($source);

        return $release;
    }
}
