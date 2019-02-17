<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class PaymentTerm.
 */
class PaymentTerm extends BaseModel
{
    use SoftDeletes;

    /**
     * @var bool
     */
    public $timestamps = true;
    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    public function getNumDays()
    {
        return $this->num_days == -1 ? 0 : $this->num_days;
    }

    public static function getCompanyTerms()
    {
        $default_terms = collect(unserialize(CACHED_PAYMENT_TERMS));

        $terms = self::scope()->get();

        $terms->map(function($term) {
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
