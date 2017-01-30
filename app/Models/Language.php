<?php

namespace App\Models;

use Eloquent;

/**
 * Class Language.
 */
class Language extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }
}
