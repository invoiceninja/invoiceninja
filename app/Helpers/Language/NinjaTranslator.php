<?php

namespace App\Helpers\Language;

use Illuminate\Support\Arr;
use Illuminate\Translation\Translator;

class NinjaTranslator extends Translator
{
    /**
     * Set translation.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  string  $locale
     * @return void
     */
    public function set($key, $value, $locale = null)
    {
        list($namespace, $group, $item) = $this->parseKey($key);

        if (null === $locale) {
            $locale = $this->locale;
        }

        // Load given group defaults if exists
        $this->load($namespace, $group, $locale);

        Arr::set($this->loaded[$namespace][$group][$locale], $item, $value);
    }

    public function replace($items, $locale = null)
    {
        if (null === $locale) {
            $locale = $this->locale;
        }

        foreach ($items as $key => $value) {
            list($namespace, $group, $item) = $this->parseKey($key);

            $this->load($namespace, $group, $locale);

            Arr::set($this->loaded[$namespace][$group][$locale], $item, $value);
        }
    }
}
