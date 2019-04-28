<?php

namespace App\Models;

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;

class Proposal extends BaseModel
{
    use MakesHash;

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

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

}
