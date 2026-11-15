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
 * Self-update's handling of the Flutter web app (SelfUpdateController::update calls apply()),
 * on a temporary install and gzipped tar archives shaped like the release. A build lives in
 * public/flutter/<id>/, the folder its boot page's base href names; the installed app keeps
 * working through every failure and interrupted update.
 */
class FlutterWebUpdateTest extends TestCase
{
    private const OLDER = '000000000000';

    private const OLD = 'aaaaaaaa0000';

    private const NEW = 'bbbbbbbb1111';

    private string $dir;

    private string $base;

    private string $staging;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/flutter-web-update-'.uniqid();
        $this->base = $this->dir.'/install';
        $this->staging = $this->dir.'/staging';

        $this->writeFiles($this->base, [
            'public/flutter/'.self::OLD.'/flutter_bootstrap.js' => 'old bootstrap',
            'public/flutter/'.self::OLD.'/old_only.js' => 'old',
            'resources/views/flutter/index.html' => $this->page(self::OLD),
            'app/Kept.php' => 'old app code',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['public', 'resources/views', 'resources/views/flutter', 'zzz'] as $folder) {
            @chmod($this->base.'/'.$folder, 0755);
        }

        @chmod($this->base.'/resources/views/flutter/index.html', 0644);

        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function testTheNewBuildIsServedAndThePreviousOneKeptForOpenTabs()
    {
        $this->update()->apply($this->fullRelease());

        $this->assertServesTheNewApp();
        $this->assertBuilds([self::NEW, self::OLD]);
        $this->assertNoLeftovers();
    }

    public function testOnlyOnePreviousBuildIsKept()
    {
        $this->writeFiles($this->base, ['public/flutter/'.self::OLDER.'/flutter_bootstrap.js' => 'older']);

        $this->update()->apply($this->fullRelease());

        $this->assertServesTheNewApp();
        $this->assertBuilds([self::NEW, self::OLD]);
    }

    public function testAReleaseWithoutTheAppLeavesTheInstalledOne()
    {
        $this->update()->apply($this->release('without', ['app/Kept.php' => 'new app code']));

        $this->assertServesTheOldApp();
        $this->assertSame('new app code', file_get_contents($this->base.'/app/Kept.php'));
        $this->assertNoLeftovers();
    }

    public function testAnIncompleteCopyKeepsTheInstalledAppAndTheUpdateGoesOn()
    {
        $this->update()->apply($this->release('incomplete', [
            'public/flutter/'.self::NEW.'/flutter_bootstrap.js' => 'new bootstrap',
            'app/Kept.php' => 'new app code',
        ]));

        $this->assertServesTheOldApp();
        $this->assertBuilds([self::OLD]);
        $this->assertSame('new app code', file_get_contents($this->base.'/app/Kept.php'));
        $this->assertNoLeftovers();
    }

    public function testACopyWithoutItsBootPageKeepsTheInstalledApp()
    {
        $this->update()->apply($this->release('no-boot-page', [
            'public/flutter/'.self::NEW.'/flutter_bootstrap.js' => 'new bootstrap',
            'resources/views/flutter/other.html' => 'not the boot page',
        ]));

        $this->assertServesTheOldApp();
        $this->assertBuilds([self::OLD]);
        $this->assertFileDoesNotExist($this->base.'/resources/views/flutter/other.html');
        $this->assertNoLeftovers();
    }

    public function testACopyWhoseBootPageNamesAMissingFolderKeepsTheInstalledApp()
    {
        $this->update()->apply($this->release('mismatched', [
            'public/flutter/'.self::NEW.'/flutter_bootstrap.js' => 'new bootstrap',
            'resources/views/flutter/index.html' => $this->page('cccccccc2222'),
        ]));

        $this->assertServesTheOldApp();
        $this->assertBuilds([self::OLD]);
        $this->assertNoLeftovers();
    }

    public function testAFailedExtractionPutsTheInstalledAppBack()
    {
        $this->expectExtractionToFailAfterTheApp();

        try {
            $this->update()->apply($this->failingRelease());
            $this->fail('The extraction did not fail.');
        } catch (\Throwable $e) {
            $this->assertStringNotContainsString('did not fail', $e->getMessage());
        }

        $this->assertServesTheOldApp();
        $this->assertBuilds([self::OLD]);
        $this->assertNoLeftovers();
    }

    public function testAFailedFirstInstallLeavesNothingHalfInstalled()
    {
        File::deleteDirectory($this->base.'/public/flutter');
        File::deleteDirectory($this->base.'/resources/views/flutter');
        $this->expectExtractionToFailAfterTheApp();

        try {
            $this->update()->apply($this->failingRelease());
        } catch (\Throwable) {
        }

        $this->assertFileDoesNotExist($this->base.'/resources/views/flutter/index.html');
        $this->assertDirectoryDoesNotExist($this->base.'/public/flutter');
        $this->assertNoLeftovers();
    }

    public function testReadOnlyParentFoldersDontMatter()
    {
        // Nothing is renamed, so read-only parents are fine.
        $this->skipIfRoot();
        chmod($this->base.'/public', 0555);
        chmod($this->base.'/resources/views', 0555);

        $this->update()->apply($this->fullRelease());

        $this->assertServesTheNewApp();
        $this->assertBuilds([self::NEW, self::OLD]);
        $this->assertNoLeftovers();
    }

    public function testAReadOnlyBootPageFolderDoesntStopTheUpdate()
    {
        // No new file can go in resources/views/flutter (the saved copy stays in memory), but the
        // page itself can still be rewritten, as the release's extraction does.
        $this->skipIfRoot();
        chmod($this->base.'/resources/views/flutter', 0555);

        $this->update()->apply($this->fullRelease());

        $this->assertServesTheNewApp();
        $this->assertNoLeftovers();
    }

    public function testAReadOnlyBootPageFolderStillGetsThePagePutBack()
    {
        $this->skipIfRoot();
        chmod($this->base.'/resources/views/flutter', 0555);

        $this->update()->apply($this->release('mismatched', [
            'public/flutter/'.self::NEW.'/flutter_bootstrap.js' => 'new bootstrap',
            'resources/views/flutter/index.html' => $this->page('cccccccc2222'),
            'app/Kept.php' => 'new app code',
        ]));

        $this->assertServesTheOldApp();
        $this->assertBuilds([self::OLD]);
        $this->assertSame('new app code', file_get_contents($this->base.'/app/Kept.php'));
    }

    public function testAPageThatCantBePutBackIsLeftForTheNextUpdate()
    {
        $this->skipIfRoot();
        $this->interruptAfterSaving();
        $this->writeFiles($this->base, ['resources/views/flutter/index.html' => '<base href="__IN_BASE_HR']);
        // The page can't be written either way (a folder where its temporary file goes, and the
        // page read-only), while the folder itself stays writable.
        File::ensureDirectoryExists($this->base.'/resources/views/flutter/index.html.tmp');
        chmod($this->base.'/resources/views/flutter/index.html', 0444);

        // The update goes on, and keeps the saved page.
        $this->update()->apply($this->release('without', ['app/Kept.php' => 'new app code']));
        $this->assertSame('new app code', file_get_contents($this->base.'/app/Kept.php'));
        $this->assertFileExists($this->base.'/resources/views/flutter/index.html.previous');

        File::deleteDirectory($this->base.'/resources/views/flutter/index.html.tmp');
        chmod($this->base.'/resources/views/flutter/index.html', 0644);
        $this->update()->apply($this->release('without', ['app/Kept.php' => 'newer app code']));

        $this->assertServesTheOldApp();
        $this->assertNoLeftovers();
    }

    public function testAnUpdateAfterOneInterruptedMidExtractionGoesThrough()
    {
        // Died with the new build half-written and the page still the old one.
        $this->interruptAfterSaving();
        $this->writeFiles($this->base, ['public/flutter/'.self::NEW.'/main.dart.js' => 'half-written']);

        $this->update()->apply($this->fullRelease());

        $this->assertServesTheNewApp();
        $this->assertBuilds([self::NEW, self::OLD]);
        $this->assertNoLeftovers();
    }

    public function testAPageHalfWrittenByAnInterruptedUpdateIsPutBack()
    {
        $this->interruptAfterSaving();
        $this->writeFiles($this->base, ['resources/views/flutter/index.html' => '<base href="__IN_BASE_HR']);

        $this->update()->apply($this->release('without', ['app/Kept.php' => 'new app code']));

        $this->assertServesTheOldApp();
        $this->assertNoLeftovers();
    }

    public function testAnUpdateInterruptedAfterItsPageLandedKeepsTheNewApp()
    {
        // Died in finish(): the new page and build are whole, the saved page and old build remain.
        $this->interruptAfterSaving();
        $this->fullRelease()->extractTo($this->base, null, true);

        $this->update()->apply($this->release('without', ['app/Kept.php' => 'new app code']));

        $this->assertServesTheNewApp();
        $this->assertNoLeftovers();
    }

    public function testAPartlyDeletedOlderBuildIsPrunedByTheNextUpdate()
    {
        // As an update that died in finish() while deleting an older build leaves it.
        $this->writeFiles($this->base, ['public/flutter/'.self::OLDER.'/half.js' => 'half-deleted']);

        $this->update()->apply($this->fullRelease());

        $this->assertServesTheNewApp();
        $this->assertBuilds([self::NEW, self::OLD]);
    }

    /** An update that saved the page, then died before finishing. */
    private function interruptAfterSaving(): void
    {
        $update = $this->update();
        $this->assertTrue($update->stage($this->fullRelease()));
        $update->save();
        $this->assertFileExists($this->base.'/resources/views/flutter/index.html.previous');
    }

    /** The release's last entry (zzz/) can't be written, so extractTo throws after the app landed. */
    private function expectExtractionToFailAfterTheApp(): void
    {
        $this->skipIfRoot();
        File::ensureDirectoryExists($this->base.'/zzz');
        chmod($this->base.'/zzz', 0555);
    }

    private function skipIfRoot(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('root writes to read-only folders');
        }
    }

    private function update(): FlutterWebUpdate
    {
        return new FlutterWebUpdate($this->base, $this->staging);
    }

    /** A boot page whose base href names build folder $id, as render_boot_page.py --server writes it. */
    private function page(string $id): string
    {
        return "<!DOCTYPE html>\n<head>\n  <base href=\"__IN_BASE_HREF__{$id}/\">\n</head>\n";
    }

    private function assertServesTheOldApp(): void
    {
        $this->assertSame($this->page(self::OLD), file_get_contents($this->base.'/resources/views/flutter/index.html'));
        $this->assertSame('old bootstrap', file_get_contents($this->base.'/public/flutter/'.self::OLD.'/flutter_bootstrap.js'));
        $this->assertSame('old', file_get_contents($this->base.'/public/flutter/'.self::OLD.'/old_only.js'));
    }

    private function assertServesTheNewApp(): void
    {
        $this->assertSame($this->page(self::NEW), file_get_contents($this->base.'/resources/views/flutter/index.html'));
        $this->assertSame('new bootstrap', file_get_contents($this->base.'/public/flutter/'.self::NEW.'/flutter_bootstrap.js'));
        $this->assertFileExists($this->base.'/public/flutter/'.self::NEW.'/assets/new.png');
    }

    /** @param array<string> $expected The build folders public/flutter should hold, and nothing else. */
    private function assertBuilds(array $expected): void
    {
        $actual = array_values(array_diff((array) scandir($this->base.'/public/flutter'), ['.', '..']));
        sort($actual);
        sort($expected);
        $this->assertSame($expected, $actual);
    }

    private function assertNoLeftovers(): void
    {
        $views = $this->base.'/resources/views/flutter';
        $this->assertFileDoesNotExist($views.'/index.html.previous');
        $this->assertFileDoesNotExist($views.'/index.html.tmp');
        $this->assertDirectoryDoesNotExist($this->staging);
    }

    private function fullRelease(): \PharData
    {
        return $this->release('full', [
            'public/flutter/'.self::NEW.'/flutter_bootstrap.js' => 'new bootstrap',
            'public/flutter/'.self::NEW.'/assets/new.png' => 'new',
            'resources/views/flutter/index.html' => $this->page(self::NEW),
            'app/Kept.php' => 'new app code',
        ]);
    }

    private function failingRelease(): \PharData
    {
        return $this->release('failing', [
            'public/flutter/'.self::NEW.'/flutter_bootstrap.js' => 'new bootstrap',
            'public/flutter/'.self::NEW.'/assets/new.png' => 'new',
            'resources/views/flutter/index.html' => $this->page(self::NEW),
            'zzz/blocked.txt' => 'cannot be written',
        ]);
    }

    /** @param array<string, string> $files */
    private function writeFiles(string $root, array $files): void
    {
        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname($root.'/'.$path));
            File::put($root.'/'.$path, $contents);
        }
    }

    /**
     * Gzipped, with folder entries and no ./ prefix, as the real release is (tar -zcvf … *,
     * named .tar).
     *
     * @param array<string, string> $files
     */
    private function release(string $name, array $files): \PharData
    {
        $source = $this->dir.'/'.$name.'-source';
        File::deleteDirectory($source);
        $this->writeFiles($source, $files);

        $path = $this->dir.'/'.$name.'.tar';
        @unlink($path);
        // COPYFILE_DISABLE: no macOS ._ metadata entries.
        exec('cd '.escapeshellarg($source).' && COPYFILE_DISABLE=1 tar -zcf '.escapeshellarg($path).' *', $output, $status);
        $this->assertSame(0, $status, 'tar failed');

        return new \PharData($path);
    }
}
