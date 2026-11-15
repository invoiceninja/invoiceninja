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
 * Self-update's handling of the Flutter web app (public/flutter and its boot page in
 * resources/views/flutter), which a release carries only when react_release.yml could add it.
 * The installed copy is kept until a new one has landed whole. apply() runs it all:
 *
 * 1. recover():  a <folder>.previous left by an interrupted update is put back first.
 * 2. stage():    the release's copy is extracted to a staging folder and checked.
 * 3. setAside(): for a release that carries the app, the installed copy is renamed to
 *                <folder>.previous, so the release extracts into empty folders.
 * 4. The whole release is extracted.
 * 5. finish():   a copy that staged whole and landed whole replaces the previous one; otherwise
 *                the previous copy is put back. restore() puts it back when step 4 throws.
 *
 * The invariant that makes an interruption safe: a .previous, while it exists, is always the
 * whole installed copy. It is made by one rename and retired by another (to .discard) before it
 * is deleted.
 *
 * A release without the app, or with a broken one, leaves the installed copy as it is: the app
 * reads the server's x-minimum-client-version and asks to be updated if it is too old.
 */
class FlutterWebUpdate
{
    /** Relative to the install, each replaced whole. */
    public const DIRECTORIES = ['public/flutter', 'resources/views/flutter'];

    /** The boot page, which names the build's folder under public/flutter/ (Ninja::flutterWebBuildId()). */
    public const BOOT_PAGE = 'resources/views/flutter/index.html';

    /** Whether the release has any of the app, and whether what it has staged whole. */
    private bool $carried = false;

    private bool $valid = false;

    /** @var list<string> The folders setAside() took charge of: renamed, or absent. */
    private array $setAside = [];

    public function __construct(private string $base, private string $staging)
    {
    }

    /**
     * Extracts the release over the install. Throws only what the extraction throws, after
     * putting the installed copy back.
     */
    public function apply(\PharData $release): void
    {
        $this->recover();
        $this->stage($release);

        if ($this->carried) {
            $this->setAside();
        }

        try {
            $release->extractTo($this->base, null, true);
        } catch (\Throwable $e) {
            $this->restore();

            throw $e;
        }

        $this->finish();
    }

    /** Puts back any .previous an interrupted update left, and clears its .discard. */
    public function recover(): void
    {
        foreach (self::DIRECTORIES as $directory) {
            $live = $this->path($directory);
            File::deleteDirectory($live.'.discard');

            if (is_dir($live.'.previous')) {
                // The .previous is whole; the live folder may be half of a newer copy.
                File::deleteDirectory($live);

                if (! @rename($live.'.previous', $live)) {
                    $this->warn("could not put {$directory}.previous back after an interrupted update");
                }
            }
        }
    }

    /**
     * Checks the release's copy before anything in the install changes. Returns whether the
     * release carries the app; a copy that can't be extracted whole is logged and left out.
     */
    public function stage(\PharData $release): bool
    {
        $this->valid = false;
        $this->carried = false;

        foreach ([...self::DIRECTORIES, self::BOOT_PAGE] as $path) {
            if (isset($release[$path])) {
                $this->carried = true;
            }
        }

        if (! $this->carried) {
            return false;
        }

        File::deleteDirectory($this->staging);

        try {
            // A trailing slash extracts the folder's entries, with or without folder entries in the archive.
            $release->extractTo($this->staging, array_map(fn (string $directory) => $directory.'/', self::DIRECTORIES), true);

            if (! $this->whole($this->staging)) {
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

    /**
     * Returns false when a folder can't be renamed (its parent isn't writable): whatever was
     * renamed is put back, and the release then extracts over the installed copy, as it always has.
     */
    public function setAside(): bool
    {
        foreach (self::DIRECTORIES as $directory) {
            $live = $this->path($directory);

            if (is_dir($live) && ! @rename($live, $live.'.previous')) {
                $this->warn("could not set {$directory} aside, so the release extracts over it");
                $this->restore();

                return false;
            }

            // Absent folders too: restore() must clear a half-extracted copy there.
            $this->setAside[] = $directory;
        }

        return true;
    }

    /**
     * Puts each folder setAside() took back as it was, dropping whatever was extracted in its
     * place. Never throws: it runs while another failure is being handled.
     */
    public function restore(): void
    {
        foreach ($this->setAside as $directory) {
            $live = $this->path($directory);

            File::deleteDirectory($live);

            if (is_dir($live.'.previous') && ! @rename($live.'.previous', $live)) {
                $this->warn("could not put {$directory} back; the next update will (it is in {$directory}.previous)");
            }
        }

        $this->setAside = [];
    }

    /**
     * After the release is extracted. Returns false when the previous copy was put back, because
     * the release's copy didn't stage whole or didn't land whole.
     */
    public function finish(): bool
    {
        if ($this->setAside === []) {
            return true;
        }

        if (! $this->valid || ! $this->whole($this->base)) {
            $this->restore();

            return false;
        }

        foreach ($this->setAside as $directory) {
            $previous = $this->path($directory).'.previous';

            if (! is_dir($previous)) {
                continue;
            }

            // Retired by one rename first, so a .previous never exists half-deleted.
            if (@rename($previous, $this->path($directory).'.discard')) {
                File::deleteDirectory($this->path($directory).'.discard');
            } else {
                // Left in place, the next update's recover() would bring the old copy back.
                $this->warn("could not retire {$directory}.previous, so it is deleted in place");
                File::deleteDirectory($previous);
            }
        }

        $this->setAside = [];

        return true;
    }

    /** What Ninja::hasFlutterWebBuild() asks of an install, asked of a folder laid out like one. */
    private function whole(string $root): bool
    {
        $page = $root.'/'.self::BOOT_PAGE;
        $id = is_file($page) ? Ninja::flutterWebBuildId((string) file_get_contents($page)) : null;

        return $id !== null && is_file("{$root}/public/flutter/{$id}/flutter_bootstrap.js");
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
