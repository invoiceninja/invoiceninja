<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proposal extends BaseModel
{

	protected $guarded = [
		'id',
	];

    protected $appends = ['proposal_id'];

    public function getRouteKeyName()
    {
        return 'proposal_id';
    }

    public function getProposalIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }


}
