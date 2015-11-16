<?php namespace App\Models;

trait OwnedByClientTrait
{
    public function isClientTrashed()
    {
        if (!$this->client) {
            return false;
        }

        return $this->client->trashed();
    }
}