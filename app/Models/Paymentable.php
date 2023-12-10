<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Paymentable
 *
 * @property int $id
 * @property int $payment_id
 * @property int $paymentable_id
 * @property float $amount
 * @property float $refunded
 * @property string $paymentable_type
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property-read \App\Models\Payment $payment
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $paymentable
 * @method static \Illuminate\Database\Eloquent\Builder|Paymentable newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Paymentable newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Paymentable onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Paymentable query()
 * @method static \Illuminate\Database\Eloquent\Builder|Paymentable whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Paymentable whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Paymentable whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Paymentable whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Paymentable wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Paymentable wherePaymentableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Paymentable wherePaymentableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Paymentable whereRefunded($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Paymentable whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Paymentable withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Paymentable withoutTrashed()
 * @mixin \Eloquent
 */
class Paymentable extends Pivot
{
    use SoftDeletes;

    protected $table = 'paymentables';

    protected $casts = [
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'settings' => 'object',
    ];

    public function paymentable()
    {
        return $this->morphTo();
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
