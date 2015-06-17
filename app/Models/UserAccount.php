<?php namespace App\Models;

use Eloquent;

class UserAccount extends Eloquent
{
    public $timestamps = false;

    public function hasUserId($userId)
    {
        if (!$userId) {
            return false;
        }

        for ($i=1; $i<=5; $i++) {
            $field = "user_id{$i}";
            if ($this->$field && $this->$field == $userId) {
                return true;
            }
        }
        return false;
    }

    public function setUserId($userId)
    {
        if (self::hasUserId($userId)) {
            return;
        }

        for ($i=1; $i<=5; $i++) {
            $field = "user_id{$i}";
            if (!$this->$field) {
                $this->$field = $userId;
                break;
            }
        }
    }

    public function removeUserId($userId)
    {
        if (!$userId || !self::hasUserId($userId)) {
            return;
        }

        for ($i=1; $i<=5; $i++) {
            $field = "user_id{$i}";
            if ($this->$field && $this->$field == $userId) {
                $this->$field = null;
            }
        }
    }
}
