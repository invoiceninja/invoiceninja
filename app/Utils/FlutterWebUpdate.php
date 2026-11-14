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
 * 1. stage():    the release's copy is extracted to a staging folder and checked, before
 *                anything in the install changes.
 * 2. setAside(): the installed copy is renamed to <folder>.previous, untouched from here on.
 * 3. The rest of the release is extracted as before.
 * 4. install():  the staged copy is renamed into place and the previous one deleted, or, if
 *                that fails, the previous one is put back. restore() puts it back when step 3
 *                fails.
 *
 * A release without the app removes the installed one, so `/` falls back to the app every
 * release carries rather than an old build served against a newer API.
 */
class FlutterWebUpdate
{
    /** Relative to the install, each replaced whole. */
    public const DIRECTORIES = ['public/flutter', 'resources/views/flutter'];

    /** What a usable copy holds: the files Ninja::hasFlutterWebBuild() looks for. */
    public const REQUIRED = ['public/flutter/flutter_bootstrap.js', 'resources/views/flutter/index.html'];

    private bool $staged = false;

    /** @var list<string> The folders setAside() has taken charge of, moved or absent. */
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
            return $this->staged = false;
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
            File::deleteDirectory($this->staging);

            throw new \RuntimeException("The release's Flutter web app could not be staged: {$e->getMessage()}", 0, $e);
        }

        return $this->staged = true;
    }

    public function setAside(): void
    {
        foreach (self::DIRECTORIES as $directory) {
            $live = $this->path($directory);
            File::deleteDirectory($live.'.previous');

            if (is_dir($live) && ! $this->move($live, $live.'.previous')) {
                throw new \RuntimeException("Could not move {$directory} aside.");
            }

            $this->setAside[] = $directory;
        }
    }

    /**
     * Puts each folder setAside() took back as it was: the previous copy, or none if there was
     * none. Whatever was extracted in its place is dropped. A folder it never took is left alone.
     */
    public function restore(): void
    {
        foreach ($this->setAside as $directory) {
            $live = $this->path($directory);
            File::deleteDirectory($live);

            if (is_dir($live.'.previous')) {
                $this->move($live.'.previous', $live);
            }
        }

        File::deleteDirectory($this->staging);
    }

    /**
     * Returns false when the staged copy couldn't be put in place; the previous one is then
     * restored, and the rest of the update should carry on.
     */
    public function install(): bool
    {
        foreach (self::DIRECTORIES as $directory) {
            $live = $this->path($directory);

            // What the release extraction wrote here is the same copy, extracted in place.
            File::deleteDirectory($live);

            if ($this->staged && ! $this->move($this->staging.'/'.$directory, $live)) {
                $this->restore();

                return false;
            }
        }

        foreach (self::DIRECTORIES as $directory) {
            File::deleteDirectory($this->path($directory).'.previous');
        }

        File::deleteDirectory($this->staging);

        return true;
    }

    private function path(string $directory): string
    {
        return $this->base.'/'.$directory;
    }

    /** A rename where the filesystem allows it, else a copy (public/ can be a separate Docker volume). */
    private function move(string $from, string $to): bool
    {
        if (! is_dir($from)) {
            return false;
        }

        if (@rename($from, $to)) {
            return true;
        }

        if (! File::copyDirectory($from, $to)) {
            File::deleteDirectory($to);

            return false;
        }

        File::deleteDirectory($from);

        return true;
    }
}
