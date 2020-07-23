<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;

class Proposal extends BaseModel
{
    use MakesHash;

    protected $guarded = [
        'id',
    ];

    protected $touches = [];

    public function getEntityType()
    {
        return Proposal::class;
    }

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

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id');
    }
}
