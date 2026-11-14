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

/**
 * Self-update's handling of the Flutter web app (public/flutter and its boot page in
 * resources/views/flutter), which a release carries only when react_release.yml could add it.
 *
 * 1. stage():    the release's copy is extracted to a staging folder and checked, before anything
 *                in the install changes. A broken copy stops the update there.
 * 2. setAside(): for a release that carries the app, the installed copy is renamed to
 *                <folder>.previous, so the release extracts into empty folders.
 * 3. The whole release is extracted, as before.
 * 4. finish():   the new copy is checked where it landed, and .previous deleted; if it is
 *                incomplete, the previous copy is put back. restore() puts it back when step 3
 *                fails.
 *
 * A release without the app leaves the installed copy as it is: the app reads the server's
 * x-minimum-client-version and asks to be updated if it is too old.
 */
class FlutterWebUpdate
{
    /** Relative to the install, each replaced whole. */
    public const DIRECTORIES = ['public/flutter', 'resources/views/flutter'];

    /** What a usable copy holds: the files Ninja::hasFlutterWebBuild() looks for. */
    public const REQUIRED = ['public/flutter/flutter_bootstrap.js', 'resources/views/flutter/index.html'];

    /** @var list<string> The folders setAside() took charge of: renamed, or absent. */
    private array $setAside = [];

    public function __construct(private string $base, private string $staging)
    {
    }

    /**
     * Returns false for a release without the app. Throws, having changed nothing in the
     * install, for a release whose copy can't be extracted whole.
     */
    public function stage(\PharData $release): bool
    {
        File::deleteDirectory($this->staging);

        $carried = array_filter(self::REQUIRED, fn (string $path) => isset($release[$path]));

        if ($carried === []) {
            return false;
        }

        try {
            // A trailing slash extracts the folder's entries, with or without folder entries in the archive.
            $release->extractTo($this->staging, array_map(fn (string $directory) => $directory.'/', self::DIRECTORIES), true);

            foreach (self::REQUIRED as $path) {
                if (! is_file($this->staging.'/'.$path)) {
                    throw new \RuntimeException("The release's Flutter web app has no {$path}.");
                }
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException("The release's Flutter web app could not be staged: {$e->getMessage()}", 0, $e);
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
            $previous = $live.'.previous';

            if (! is_dir($live)) {
                // Nothing to rename, but restore() must still clear a half-extracted copy here. A
                // .previous with no live folder is what an interrupted update leaves: the only copy, kept.
                $this->setAside[] = $directory;

                continue;
            }

            File::deleteDirectory($previous);

            if (! @rename($live, $previous)) {
                nlog("Flutter web app: could not set {$directory} aside, so the release extracts over it");
                $this->restore();

                return false;
            }

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
                nlog("Flutter web app: could not put {$directory} back; the previous copy is in {$directory}.previous");
            }
        }

        $this->setAside = [];
    }

    /**
     * After the release is extracted. Returns false when the new copy is incomplete, in which
     * case the previous one is back in place.
     */
    public function finish(): bool
    {
        if ($this->setAside === []) {
            return true;
        }

        foreach (self::REQUIRED as $path) {
            if (! is_file($this->path($path))) {
                nlog("Flutter web app: the release extracted no {$path}, so the previous copy was put back");
                $this->restore();

                return false;
            }
        }

        foreach ($this->setAside as $directory) {
            File::deleteDirectory($this->path($directory).'.previous');
        }

        $this->setAside = [];

        return true;
    }

    private function path(string $relative): string
    {
        return $this->base.'/'.$relative;
    }
}
