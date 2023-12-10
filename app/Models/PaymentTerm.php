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

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class PaymentTerm.
 *
 * @property int $id
 * @property int|null $num_days
 * @property string|null $name
 * @property int|null $company_id
 * @property int|null $user_id
 * @property int $is_deleted
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property-read mixed $hashed_id
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentTerm filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentTerm newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentTerm newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentTerm onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentTerm query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentTerm whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentTerm whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentTerm whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentTerm whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentTerm whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentTerm whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentTerm whereNumDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentTerm whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentTerm whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentTerm withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentTerm withoutTrashed()
 * @mixin \Eloquent
 */
class PaymentTerm extends BaseModel
{
    use SoftDeletes;
    use Filterable;

    /**
     * @var bool
     */
    public $timestamps = true;

    protected $fillable = ['num_days'];

    public function getNumDays()
    {
        return $this->num_days == -1 ? 0 : $this->num_days;
    }

    public static function getCompanyTerms()
    {
        $default_terms = collect(config('ninja.payment_terms'));

        $terms = self::whereCompanyId(auth()->user()->company()->id)->orWhere('company_id', null)->get();

        $terms->map(function ($term) {
            return $term['num_days'];
        });

        $default_terms->merge($terms)
        ->sort()
        ->values()
        ->all();

        return $default_terms;
    }

    public static function getSelectOptions()
    {
        /*
        $terms = PaymentTerm::whereAccountId(0)->get();

        foreach (PaymentTerm::scope()->get() as $term) {
            $terms->push($term);
        }

        foreach ($terms as $term) {
            $term->name = trans('texts.payment_terms_net') . ' ' . $term->getNumDays();
        }

        return $terms->sortBy('num_days');
        */
    }
}
