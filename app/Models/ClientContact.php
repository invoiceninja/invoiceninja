<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Utils\Traits\MakesHash;
use Hashids\Hashids;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laracasts\Presenter\PresentableTrait;


class ClientContact extends Authenticatable
{
    use Notifiable;
    use MakesHash;
    use PresentableTrait;
    use SoftDeletes;

   // protected $appends = ['contact_id'];

    protected $guard = 'contact';

    protected $presenter = 'App\Models\Presenters\ClientContactPresenter';

    protected $dates = ['deleted_at'];
    
    protected $guarded = [
        'id',
    ];
   
    protected $hidden = [
        'password', 
        'remember_token',
    ];

    
    public function getRouteKeyName()
    {
        return 'contact_id';
    }

    public function getContactIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function client()
    {
        $this->hasOne(Client::class);
    }

    public function primary_contact()
    {
        $this->where('is_primary', true);
    }

}
