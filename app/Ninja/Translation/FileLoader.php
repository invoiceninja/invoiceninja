<?php

namespace App\Ninja\Translation;

use Illuminate\Filesystem\Filesystem;

class FileLoader extends \Illuminate\Translation\FileLoader
{

    /**
     * The overlay path for the loader.
     *
     * @var string
     */
    protected $overlayPath;

    /**
     * Create a new file loader instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  string  $path
     * @param  string  $overlayPath
     * @return void
     */
    public function __construct(Filesystem $files, $path, $overlayPath)
    {
        parent::__construct($files, $path);
        $this->overlayPath = $overlayPath;
    }

    /**
     * Load the messages for the given locale.
     *
     * @param  string  $locale
     * @param  string  $group
     * @param  string  $namespace
     * @return array
     */
    public function load($locale, $group, $namespace = null)
    {
        $parent = parent::load($locale, $group, $namespace);

        if ($group == '*' && $namespace == '*') {
            return array_merge($parent, $this->loadJsonPath($this->overlayPath, $locale));
        }

        if (is_null($namespace) || $namespace == '*') {
            return array_merge($parent, $this->loadPath($this->overlayPath, $locale, $group));
        }

        return array_merge($parent, $this->loadNamespaced($locale, $group, $namespace));
    }
}