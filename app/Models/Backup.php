<?php

namespace App\Models;

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

class Backup extends BaseModel
{
	public function activity()
	{
		return $this->belongsTo(Activity::class);
	}
}