<?php

namespace App\Models\Traits;

use Utils;
use Illuminate\Support\Facades\Storage;

/**
 * Class HasLogo.
 */
trait HasLogo
{
    /**
     * @return bool
     */
    public function hasLogo()
    {
        return ! empty($this->logo);
    }

    /**
     * @return mixed
     */
    public function getLogoDisk()
    {
        return Storage::disk(env('LOGO_FILESYSTEM', 'logos'));
    }

    protected function calculateLogoDetails()
    {
        $disk = $this->getLogoDisk();

        if ($disk->exists($this->account_key.'.png')) {
            $this->logo = $this->account_key.'.png';
        } elseif ($disk->exists($this->account_key.'.jpg')) {
            $this->logo = $this->account_key.'.jpg';
        }

        if (! empty($this->logo)) {
            $image = imagecreatefromstring($disk->get($this->logo));
            $this->logo_width = imagesx($image);
            $this->logo_height = imagesy($image);
            $this->logo_size = $disk->size($this->logo);
        } else {
            $this->logo = null;
        }
        $this->save();
    }

    /**
     * @return null
     */
    public function getLogoRaw()
    {
        if (! $this->hasLogo()) {
            return null;
        }

        $disk = $this->getLogoDisk();

        if (! $disk->exists($this->logo)) {
            return null;
        }

        return $disk->get($this->logo);
    }

    /**
     * @param bool $cachebuster
     *
     * @return null|string
     */
    public function getLogoURL($cachebuster = false)
    {
        if (! $this->hasLogo()) {
            return null;
        }

        $disk = $this->getLogoDisk();
        $adapter = $disk->getAdapter();

        if ($adapter instanceof \League\Flysystem\Adapter\Local) {
            // Stored locally
            $logoUrl = url('/logo/' . $this->logo);

            if ($cachebuster) {
                $logoUrl .= '?no_cache='.time();
            }

            return $logoUrl;
        }

        return Document::getDirectFileUrl($this->logo, $this->getLogoDisk());
    }

    public function getLogoPath()
    {
        if (! $this->hasLogo()) {
            return null;
        }

        $disk = $this->getLogoDisk();
        $adapter = $disk->getAdapter();

        if ($adapter instanceof \League\Flysystem\Adapter\Local) {
            return $adapter->applyPathPrefix($this->logo);
        } else {
            return Document::getDirectFileUrl($this->logo, $this->getLogoDisk());
        }
    }

    /**
     * @return mixed|null
     */
    public function getLogoWidth()
    {
        if (! $this->hasLogo()) {
            return null;
        }

        return $this->logo_width;
    }

    /**
     * @return mixed|null
     */
    public function getLogoHeight()
    {
        if (! $this->hasLogo()) {
            return null;
        }

        return $this->logo_height;
    }

    /**
     * @return float|null
     */
    public function getLogoSize()
    {
        if (! $this->hasLogo()) {
            return null;
        }

        return round($this->logo_size / 1000);
    }

    /**
     * @return bool
     */
    public function isLogoTooLarge()
    {
        return $this->getLogoSize() > MAX_LOGO_FILE_SIZE;
    }

    public function clearLogo()
    {
        $this->logo = '';
        $this->logo_width = 0;
        $this->logo_height = 0;
        $this->logo_size = 0;
    }
}
