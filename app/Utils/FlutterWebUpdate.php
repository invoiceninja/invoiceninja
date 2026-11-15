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

namespace App\Utils;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Self-update's handling of the Flutter web app, which a release carries only when
 * react_release.yml could add it: a build in public/flutter/<id>/, never changed once written,
 * and its boot page, resources/views/flutter/index.html, whose base href names <id>
 * (Ninja::flutterWebBuildId()). The page is the only thing that switches, so it is the only
 * thing to protect. apply() runs it all:
 *
 * 1. recover(): if the live page doesn't name a whole build, but the page an interrupted update
 *    saved does, that page is put back.
 * 2. stage():   the release's copy is extracted to a staging folder and checked.
 * 3. save():    for a release that carries the app, a whole live page is copied to
 *               index.html.previous.
 * 4. The whole release is extracted: its build lands in a new folder beside the old one, and its
 *    page over the old page.
 * 5. finish():  a copy that staged whole and landed whole stays, with the previous build kept
 *               for tabs still open on it and older ones deleted. Otherwise the saved page is
 *               put back and whatever the release added is deleted. rollBack() does the same
 *               when step 4 throws.
 *
 * Page writes are a rename of a written file, so a page is never half there. A release without
 * the app, or with a broken one, leaves the installed app as it is: the app reads the server's
 * x-minimum-client-version and asks to be updated if it is too old.
 */
class FlutterWebUpdate
{
    public const BUILDS = 'public/flutter';

    public const BOOT_PAGE = 'resources/views/flutter/index.html';

    private bool $carried = false;

    private bool $valid = false;

    /** The live page save() found naming a whole build, kept in memory too in case the copy couldn't be written. */
    private ?string $savedPage = null;

    public function __construct(private string $base, private string $staging)
    {
    }

    /**
     * Extracts the release over the install. Throws only what the extraction throws, after
     * putting the installed app back.
     */
    public function apply(\PharData $release): void
    {
        $this->recover();
        $this->stage($release);

        if (! $this->carried) {
            $release->extractTo($this->base, null, true);

            return;
        }

        $this->save();

        try {
            $release->extractTo($this->base, null, true);
        } catch (\Throwable $e) {
            $this->rollBack();

            throw $e;
        }

        $this->finish();
    }

    /** Puts back the page an interrupted update saved, if the live one doesn't name a whole build. */
    public function recover(): void
    {
        $saved = $this->path(self::BOOT_PAGE).'.previous';

        if (! is_file($saved)) {
            return;
        }

        if ($this->buildOf($this->page()) === null && $this->buildOf((string) file_get_contents($saved)) !== null) {
            $this->writePage((string) file_get_contents($saved));
            $this->warn('put back the boot page an interrupted update saved');
        }

        @unlink($saved);
    }

    /**
     * Checks the release's copy before anything in the install changes. Returns whether the
     * release carries the app; a copy that can't be extracted whole is logged and left out.
     */
    public function stage(\PharData $release): bool
    {
        $this->valid = false;
        $this->carried = isset($release[self::BUILDS]) || isset($release[self::BOOT_PAGE]);

        if (! $this->carried) {
            return false;
        }

        File::deleteDirectory($this->staging);

        try {
            // A trailing slash extracts the folder's entries, with or without folder entries in the archive.
            $release->extractTo($this->staging, [self::BUILDS.'/', dirname(self::BOOT_PAGE).'/'], true);

            if ($this->buildOf((string) @file_get_contents($this->staging.'/'.self::BOOT_PAGE), $this->staging) === null) {
                throw new \RuntimeException('its boot page names no build folder holding flutter_bootstrap.js');
            }

            $this->valid = true;
        } catch (\Throwable $e) {
            $this->warn("the release's copy can't be used, so the installed one is kept: {$e->getMessage()}");
        } finally {
            File::deleteDirectory($this->staging);
        }

        return true;
    }

    /** Copies a live page that names a whole build to index.html.previous, for finish() or a later recover(). */
    public function save(): void
    {
        $page = $this->page();
        $this->savedPage = $this->buildOf($page) !== null ? $page : null;

        if ($this->savedPage !== null) {
            $this->atomicWrite($this->path(self::BOOT_PAGE).'.previous', $page);
        }
    }

    /**
     * After the release is extracted. Returns false when the installed app was put back, because
     * the release's copy didn't stage whole or didn't land whole.
     */
    public function finish(): bool
    {
        $live = $this->buildOf($this->page());

        if ($this->valid && $live !== null) {
            $previous = $this->savedPage === null ? null : Ninja::flutterWebBuildId($this->savedPage);
            @unlink($this->path(self::BOOT_PAGE).'.previous');
            $this->prune(array_filter([$live, $previous]));

            return true;
        }

        $this->rollBack();

        return false;
    }

    /** Puts the saved page back (or, with none, clears the half-installed app) and drops what the release added. */
    public function rollBack(): void
    {
        if ($this->savedPage !== null) {
            $this->writePage($this->savedPage);
            $this->prune([(string) Ninja::flutterWebBuildId($this->savedPage)]);
        } else {
            // There was no whole app before this update, so nothing here is worth keeping.
            @unlink($this->path(self::BOOT_PAGE));
            File::deleteDirectory($this->path(self::BUILDS));
        }

        @unlink($this->path(self::BOOT_PAGE).'.previous');
    }

    /** The page's build folder, if the page names one that holds flutter_bootstrap.js under $root. */
    private function buildOf(string $page, ?string $root = null): ?string
    {
        $id = Ninja::flutterWebBuildId($page);
        $root ??= $this->base;

        return $id !== null && is_file("{$root}/".self::BUILDS."/{$id}/flutter_bootstrap.js") ? $id : null;
    }

    private function page(): string
    {
        $page = $this->path(self::BOOT_PAGE);

        return is_file($page) ? (string) file_get_contents($page) : '';
    }

    private function writePage(string $contents): void
    {
        $this->atomicWrite($this->path(self::BOOT_PAGE), $contents);
    }

    /** Written beside the target, then renamed over it: a reader sees the old file or the new one. */
    private function atomicWrite(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));
        $temporary = $path.'.tmp';

        if (file_put_contents($temporary, $contents) === false || ! @rename($temporary, $path)) {
            @unlink($temporary);
            $this->warn('could not write '.basename(dirname($path)).'/'.basename($path));
        }
    }

    /**
     * Deletes every build folder (and stray file) in public/flutter but $keep, and anything in
     * resources/views/flutter but the page and its saved copy.
     *
     * @param array<string> $keep
     */
    private function prune(array $keep): void
    {
        $this->deleteAllBut($this->path(self::BUILDS), $keep);
        $this->deleteAllBut(dirname($this->path(self::BOOT_PAGE)), [basename(self::BOOT_PAGE), basename(self::BOOT_PAGE).'.previous']);
    }

    /** @param array<string> $keep Names in $folder to leave; dotfiles included in the rest. */
    private function deleteAllBut(string $folder, array $keep): void
    {
        foreach (is_dir($folder) ? (array) scandir($folder) : [] as $name) {
            if (in_array($name, ['.', '..', ...$keep], true)) {
                continue;
            }

            is_dir("{$folder}/{$name}") && ! is_link("{$folder}/{$name}")
                ? File::deleteDirectory("{$folder}/{$name}")
                : @unlink("{$folder}/{$name}");
        }
    }

    private function path(string $relative): string
    {
        return $this->base.'/'.$relative;
    }

    /** Logged whatever the logging setting (nlog is silent by default). Never throws. */
    private function warn(string $message): void
    {
        try {
            Log::warning('Self-update, Flutter web app: '.$message);
        } catch (\Throwable) {
        }
    }
}
