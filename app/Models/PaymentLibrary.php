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

/**
 * Class PaymentLibrary.
 *
 * @property int $id
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property string|null $name
 * @property bool $visible
 * @property-read mixed $hashed_id
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentLibrary newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentLibrary newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentLibrary query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentLibrary whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentLibrary whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentLibrary whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentLibrary whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentLibrary whereVisible($value)
 * @mixin \Eloquent
 */
class PaymentLibrary extends BaseModel
{
    protected $casts = [
        'visible' => 'boolean',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];
}
