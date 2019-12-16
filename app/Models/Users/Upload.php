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
class Upload extends BaseModel
{
    /** Types for file uploads. */
    const AVATAR = 'avatar';
    const ATTACHMENT = 'attachment';

    /**
     * Guarded data, for mass insert.
     *
     * @var array
     */
    protected $guarded = [];

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
