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
 * App\Models\Timezone
 *
 * @property int $id
 * @property string $name
 * @property string $location
 * @property int $utc_offset
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|Timezone newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Timezone newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Timezone query()
 * @method static \Illuminate\Database\Eloquent\Builder|Timezone whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Timezone whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Timezone whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Timezone whereUtcOffset($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Timezone find()
 * @mixin \Eloquent
 */
class Timezone extends StaticModel
{
    /**
     * @var bool
     */
    public $timestamps = false;
}
