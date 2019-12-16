<?php

namespace App\Models\Users;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Model used to store user's attachments, uploads, etc.
 *
 * @package App\Models\Users
 */
class Uploads extends BaseModel
{
    /** Types for file uploads. */
    protected static $AVATAR = 'avatar';
    protected static $ATTACHMENT = 'attachment';

    /**
     * @var string
     */
    protected $table = 'users_uploads';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return mixed
     */
    public function getUploadedAtAttribute()
    {
        return $this->created_at->diffForHumans();
    }
}
