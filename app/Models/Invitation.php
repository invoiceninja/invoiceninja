<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitation extends BaseModel
{


    public function invoices()
    {
        return $this->morphedByMany(Invoice::class, 'inviteable');
    }


    public function proposals()
    {
        return $this->morphedByMany(Proposal::class, 'inviteable');
    }

}
