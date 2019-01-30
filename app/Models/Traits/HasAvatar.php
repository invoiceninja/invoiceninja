<?php

namespace App\Models\Traits;

use Utils;
use Illuminate\Support\Facades\Storage;

/**
 * Class HasAvatar.
 */
trait HasAvatar
{
    /**
     * @return bool
     */
    public function hasAvatar()
    {
        return ! empty($this->avatar);
    }

    /**
     * @return mixed
     */
    public function getAvatarDisk()
    {
        return Storage::disk(env('LOGO_FILESYSTEM', 'logos'));
    }

    protected function calculateAvatarDetails()
    {
        $disk = $this->getAvatarDisk();

        if (! empty($this->avatar)) {
            $image = imagecreatefromstring($disk->get($this->avatar));
            $this->avatar_width = imagesx($image);
            $this->avatar_height = imagesy($image);
            $this->avatar_size = $disk->size($this->avatar);
        } else {
            $this->avatar = null;
        }
        $this->save();
    }

    /**
     * @return null
     */
    public function getAvatarRaw()
    {
        if (! $this->hasAvatar()) {
            return null;
        }

        $disk = $this->getAvatarDisk();

        if (! $disk->exists($this->avatar)) {
            return null;
        }

        return $disk->get($this->avatar);
    }

    /**
     * @param bool $cachebuster
     *
     * @return null|string
     */
    public function getAvatarURL($cachebuster = false)
    {
        if (! $this->hasAvatar()) {
            return null;
        }

        $disk = $this->getAvatarDisk();
        $adapter = $disk->getAdapter();

        if ($adapter instanceof \League\Flysystem\Adapter\Local) {
            // Stored locally
            $avatarUrl = url('/logo/' . $this->avatar);

            if ($cachebuster) {
                $avatarUrl .= '?no_cache='.time();
            }

            return $avatarUrl;
        }

        return Document::getDirectFileUrl($this->avatar, $this->getAvatarDisk());
    }

    public function getAvatarPath()
    {
        if (! $this->hasAvatar()) {
            return null;
        }

        $disk = $this->getAvatarDisk();
        $adapter = $disk->getAdapter();

        if ($adapter instanceof \League\Flysystem\Adapter\Local) {
            return $adapter->applyPathPrefix($this->avatar);
        } else {
            return Document::getDirectFileUrl($this->avatar, $this->getAvatarDisk());
        }
    }

    /**
     * @return mixed|null
     */
    public function getAvatarWidth()
    {
        if (! $this->hasAvatar()) {
            return null;
        }

        return $this->avatar_width;
    }

    /**
     * @return mixed|null
     */
    public function getLogoHeight()
    {
        if (! $this->hasAvatar()) {
            return null;
        }

        return $this->avatar_height;
    }

    /**
     * @return float|null
     */
    public function getAvatarSize()
    {
        if (! $this->hasAvatar()) {
            return null;
        }

        return round($this->avatar_size / 1000);
    }

    /**
     * @return string|null
     */
    public function getAvatarName()
    {
        if (! $this->hasAvatar()) {
            return null;
        }

        return $this->avatar;
    }

    /**
     * @return bool
     */
    public function isAvatarTooLarge()
    {
        return $this->getAvatarSize() > MAX_LOGO_FILE_SIZE;
    }

    public function clearAvatar()
    {
        $this->avatar = '';
        $this->avatar_width = 0;
        $this->avatar_height = 0;
        $this->avatar_size = 0;
    }
}
