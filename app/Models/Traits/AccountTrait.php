<?php

namespace App\Models\Traits;

trait AccountTrait
{

    public function getDisplayName()
    {
        if ($this->name) {
            return $this->name;
        }

        $user = $this->users()->first();
        
        return $user->getDisplayName();
    }
}