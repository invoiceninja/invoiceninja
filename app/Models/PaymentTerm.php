<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use App\Models\Filterable;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class PaymentTerm.
 */
class PaymentTerm extends BaseModel
{
    use SoftDeletes;
    use Filterable;

    /**
     * @var bool
     */
    public $timestamps = true;

    /**
     * @var array
     */
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
