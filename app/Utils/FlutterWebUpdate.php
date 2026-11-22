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
 * This class writes a page by renaming a written file, so its own writes are never half there
 * (the extraction's write is the release's, and recover() covers it). A release without
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
     * putting the installed app back; every other step only logs a failure.
     */
    public function apply(\PharData $release): void
    {
        $this->guarded(fn () => $this->recover());
        $this->guarded(fn () => $this->stage($release));

        if (! $this->carried) {
            $release->extractTo($this->base, null, true);

            return;
        }

        $this->guarded(fn () => $this->save());

        try {
            $release->extractTo($this->base, null, true);
        } catch (\Throwable $e) {
            $this->guarded(fn () => $this->rollBack());

            throw $e;
        }

        $this->guarded(fn () => $this->finish());
    }

    /** Puts back the page an interrupted update saved, if the live one doesn't name a whole build. */
    public function recover(): void
    {
        $saved = $this->path(self::BOOT_PAGE).'.previous';

        if (! is_file($saved)) {
            return;
        }

        $page = (string) file_get_contents($saved);

        if ($this->buildOf($this->page()) === null && $this->buildOf($page) !== null) {
            if (! $this->writePage($page)) {
                return; // Kept for the next update to try again.
            }

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

        if ($this->savedPage !== null && ! $this->atomicWrite($this->path(self::BOOT_PAGE).'.previous', $page)) {
            $this->warn('could not save the boot page to disk; it is kept in memory for this update');
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
            $id = (string) Ninja::flutterWebBuildId($this->savedPage);
            $this->writePage($this->savedPage);

            if (Ninja::flutterWebBuildId($this->page()) !== $id) {
                // The page couldn't be put back: keep every build and the saved copy, for recover().
                $this->warn('could not put the boot page back; the next update will');

                return;
            }

            $this->prune([$id]);
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

    private function writePage(string $contents): bool
    {
        $page = $this->path(self::BOOT_PAGE);

        if ($this->atomicWrite($page, $contents)) {
            return true;
        }

        // A folder that can't take a new file may still let its page be rewritten in place.
        if (is_file($page) && @file_put_contents($page, $contents, LOCK_EX) !== false) {
            return true;
        }

        $this->warn('could not write the boot page');

        return false;
    }

    /**
     * Written beside the target, then renamed over it: a reader sees the old file or the new one.
     * Returns whether it was written; never throws.
     */
    private function atomicWrite(string $path, string $contents): bool
    {
        $temporary = $path.'.tmp';

        try {
            if (@file_put_contents($temporary, $contents) !== false && @rename($temporary, $path)) {
                return true;
            }
        } catch (\Throwable) {
        }

        @unlink($temporary);

        return false;
    }

    /** Runs a step whose failure must not stop the update (the release's code is in place, migrations follow). */
    private function guarded(callable $step): void
    {
        try {
            $step();
        } catch (\Throwable $e) {
            $this->warn($e->getMessage());
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
