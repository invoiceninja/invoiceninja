<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

/**
 * App\Models\EInvoicingToken
 *
 * @package App\Models
 * @property string|null $license_key The license key string
 * @property string|null $token
 * @property string|null $account_key
 * @property \App\Models\License $license_relation
 * @mixin \Eloquent
 *
 */
use App\Models\License;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EInvoicingToken extends Model
{
    protected $fillable = [
        'license',
        'token',
        'account_key',
    ];

    /**
     * license_relation
     *
     */
    public function license_relation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(License::class, 'license', 'license_key');
    }
}
