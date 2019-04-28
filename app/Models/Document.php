<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends BaseModel
{
    public function documentable()
    {
        return $this->morphTo();
    }

}
