<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

/**
 * App\Models\EInvoicingToken
 *
 * @package App\Models
 * @property int $id
 * @property string $license
 * @property string $token
 * @property string $account_key
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\License $license_relation
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
