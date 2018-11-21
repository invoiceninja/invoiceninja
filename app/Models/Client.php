<?php

namespace App\Models;

use Laracasts\Presenter\PresentableTrait;
use Hashids\Hashids;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends BaseModel
{
    use PresentableTrait;
    use MakesHash;
    use SoftDeletes;

    protected $presenter = 'App\Models\Presenters\ClientPresenter';

    //protected $appends = ['client_id'];

    protected $guarded = [
        'id'
    ];

    public function getRouteKeyName()
    {
        return 'client_id';
    }

    public function getHashedIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function contacts()
    {
        return $this->hasMany(ClientContact::class);
    }

    public function primary_contact()
    {
        return $this->hasMany(ClientContact::class)->whereIsPrimary(true);
    }

}
