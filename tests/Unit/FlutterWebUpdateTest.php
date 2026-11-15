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
 * on a temporary install and gzipped tar archives shaped like the release: the installed copy is
 * kept until a new one has landed whole, through failures and interrupted updates.
 *
 * A build lives in public/flutter/<id>/, the folder its boot page's base href names.
 */
class FlutterWebUpdateTest extends TestCase
{
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
        foreach (['public', 'resources/views', 'zzz'] as $folder) {
            @chmod($this->base.'/'.$folder, 0755);
        }

        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function testTheNewCopyReplacesTheOldOneWhole()
    {
        $this->update()->apply($this->fullRelease());

        $this->assertInstallIsTheNewOne();
        $this->assertDirectoryDoesNotExist($this->base.'/public/flutter/'.self::OLD);
        $this->assertNoLeftovers();
    }

    public function testAReleaseWithoutTheAppKeepsTheInstalledOne()
    {
        $this->update()->apply($this->release('without', ['app/Kept.php' => 'new app code']));

        $this->assertInstallIsTheOldOne();
        $this->assertSame('new app code', file_get_contents($this->base.'/app/Kept.php'));
        $this->assertNoLeftovers();
    }

    public function testAnIncompleteCopyKeepsTheInstalledOneAndTheUpdateGoesOn()
    {
        $this->update()->apply($this->release('incomplete', [
            'public/flutter/'.self::NEW.'/flutter_bootstrap.js' => 'new bootstrap',
            'app/Kept.php' => 'new app code',
        ]));

        $this->assertInstallIsTheOldOne();
        $this->assertSame('new app code', file_get_contents($this->base.'/app/Kept.php'));
        $this->assertNoLeftovers();
    }

    public function testACopyWithoutItsBootPageKeepsTheInstalledOne()
    {
        $this->update()->apply($this->release('no-boot-page', [
            'public/flutter/'.self::NEW.'/flutter_bootstrap.js' => 'new bootstrap',
            'resources/views/flutter/other.html' => 'not the boot page',
            'app/Kept.php' => 'new app code',
        ]));

        $this->assertInstallIsTheOldOne();
        $this->assertFileDoesNotExist($this->base.'/resources/views/flutter/other.html');
        $this->assertNoLeftovers();
    }

    public function testACopyWhoseBootPageNamesAnotherFolderKeepsTheInstalledOne()
    {
        $this->update()->apply($this->release('mismatched', [
            'public/flutter/'.self::NEW.'/flutter_bootstrap.js' => 'new bootstrap',
            'resources/views/flutter/index.html' => $this->page('cccccccc2222'),
        ]));

        $this->assertInstallIsTheOldOne();
        $this->assertNoLeftovers();
    }

    public function testAFailedExtractionPutsThePreviousCopyBack()
    {
        $this->expectExtractionToFailAfterTheApp();

        try {
            $this->update()->apply($this->failingRelease());
            $this->fail('The extraction did not fail.');
        } catch (\Throwable $e) {
            $this->assertStringNotContainsString('did not fail', $e->getMessage());
        }

        $this->assertInstallIsTheOldOne();
        $this->assertNoLeftovers();
    }

    public function testAFailedFirstInstallLeavesNoHalfCopy()
    {
        File::deleteDirectory($this->base.'/public/flutter');
        File::deleteDirectory($this->base.'/resources/views/flutter');
        $this->expectExtractionToFailAfterTheApp();

        try {
            $this->update()->apply($this->failingRelease());
        } catch (\Throwable) {
        }

        $this->assertDirectoryDoesNotExist($this->base.'/public/flutter');
        $this->assertDirectoryDoesNotExist($this->base.'/resources/views/flutter');
        $this->assertNoLeftovers();
    }

    public function testAFirstFolderThatCantBeSetAsideIsExtractedOverAsBefore()
    {
        $this->skipIfRoot();
        chmod($this->base.'/public', 0555);

        $this->update()->apply($this->fullRelease());

        $this->assertInstallIsTheNewOne();
        $this->assertNoLeftovers();
    }

    public function testASecondFolderThatCantBeSetAsideUndoesTheFirst()
    {
        $this->skipIfRoot();
        chmod($this->base.'/resources/views', 0555);
        $update = $this->update();

        $update->stage($this->fullRelease());
        $this->assertFalse($update->setAside());
        $this->assertInstallIsTheOldOne();
        $this->assertNoLeftovers();

        $this->update()->apply($this->fullRelease());
        $this->assertInstallIsTheNewOne();
        $this->assertNoLeftovers();
    }

    public function testAnUpdateAfterOneInterruptedMidExtractionStartsFromThePreviousCopy()
    {
        $this->interruptAnUpdateMidExtraction();

        $this->update()->apply($this->fullRelease());

        $this->assertInstallIsTheNewOne();
        $this->assertNoLeftovers();
    }

    public function testAFailedUpdateAfterAnInterruptedOnePutsTheWholePreviousCopyBack()
    {
        $this->interruptAnUpdateMidExtraction();
        $this->expectExtractionToFailAfterTheApp();

        try {
            $this->update()->apply($this->failingRelease());
        } catch (\Throwable) {
        }

        $this->assertInstallIsTheOldOne();
        $this->assertNoLeftovers();
    }

    public function testAReleaseWithoutTheAppAfterAnInterruptedUpdateBringsThePreviousCopyBack()
    {
        $this->interruptAnUpdateMidExtraction();

        $this->update()->apply($this->release('without', ['app/Kept.php' => 'new app code']));

        $this->assertInstallIsTheOldOne();
        $this->assertNoLeftovers();
    }

    public function testAnInterruptedRestoreIsFinishedByTheNextUpdate()
    {
        // restore() died after deleting the live folder, before renaming .previous back.
        rename($this->base.'/public/flutter', $this->base.'/public/flutter.previous');

        $this->update()->apply($this->release('without', ['app/Kept.php' => 'new app code']));

        $this->assertInstallIsTheOldOne();
        $this->assertNoLeftovers();
    }

    public function testAnInterruptedFinishLeavesNothingBehind()
    {
        // finish() died while deleting a retired copy.
        $this->writeFiles($this->base, ['public/flutter.discard/half.js' => 'half-deleted']);

        $this->update()->apply($this->release('without', ['app/Kept.php' => 'new app code']));

        $this->assertInstallIsTheOldOne();
        $this->assertNoLeftovers();
    }

    /** An update that set the copy aside, then died part-way through the release's app. */
    private function interruptAnUpdateMidExtraction(): void
    {
        $update = $this->update();
        $update->stage($this->fullRelease());
        $this->assertTrue($update->setAside());
        $this->writeFiles($this->base, ['public/flutter/'.self::NEW.'/flutter_bootstrap.js' => 'half-written']);
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

    private function assertInstallIsTheOldOne(): void
    {
        $this->assertSame('old bootstrap', file_get_contents($this->base.'/public/flutter/'.self::OLD.'/flutter_bootstrap.js'));
        $this->assertSame('old', file_get_contents($this->base.'/public/flutter/'.self::OLD.'/old_only.js'));
        $this->assertSame($this->page(self::OLD), file_get_contents($this->base.'/resources/views/flutter/index.html'));
        $this->assertDirectoryDoesNotExist($this->base.'/public/flutter/'.self::NEW);
    }

    private function assertInstallIsTheNewOne(): void
    {
        $this->assertSame('new bootstrap', file_get_contents($this->base.'/public/flutter/'.self::NEW.'/flutter_bootstrap.js'));
        $this->assertSame($this->page(self::NEW), file_get_contents($this->base.'/resources/views/flutter/index.html'));
        $this->assertFileExists($this->base.'/public/flutter/'.self::NEW.'/assets/new.png');
    }

    private function assertNoLeftovers(): void
    {
        foreach (FlutterWebUpdate::DIRECTORIES as $directory) {
            $this->assertDirectoryDoesNotExist($this->base.'/'.$directory.'.previous');
            $this->assertDirectoryDoesNotExist($this->base.'/'.$directory.'.discard');
        }

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
