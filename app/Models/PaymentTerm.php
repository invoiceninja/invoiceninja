<?php

namespace App\Models;

use Cache;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class PaymentTerm.
 */
class PaymentTerm extends EntityModel
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

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_PAYMENT_TERM;
    }

    public static function getSelectOptions()
    {
        $terms = Cache::get('paymentTerms');

        foreach (PaymentTerm::scope()->get() as $term) {
            $terms->push($term);
        }

        return $terms->sortBy('num_days');
    }
}
