<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{


    public function invoices()
    {
        return $this->morphedByMany(Invoice::class, 'inviteable');
    }


    public function proposals()
    {
        return $this->morphedByMany(Proposal::class, 'taggable');
    }

}
