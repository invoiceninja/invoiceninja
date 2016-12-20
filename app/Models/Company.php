<?php namespace App\Models;

use Carbon;
use Utils;
use Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

/**
 * Class Company
 */
class Company extends Eloquent
{
    use SoftDeletes;
    use PresentableTrait;

    /**
     * @var string
     */
    protected $presenter = 'App\Ninja\Presenters\CompanyPresenter';

    /**
     * @var array
     */
    protected $dates = [
        'deleted_at',
        'promo_expires',
        'discount_expires',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function accounts()
    {
        return $this->hasMany('App\Models\Account');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function payment()
    {
        return $this->belongsTo('App\Models\Payment');
    }

    public function hasActivePromo()
    {
        if ($this->discount_expires) {
            return false;
        }

        return $this->promo_expires && $this->promo_expires->gte(Carbon::today());
    }

    // handle promos and discounts
    public function hasActiveDiscount(Carbon $date = null)
    {
        if ( ! $this->discount) {
            return false;
        }

        $date = $date ?: Carbon::today();

        return $this->discount_expires && $this->discount_expires->gt($date);
    }

    public function discountedPrice($price)
    {
        if ( ! $this->hasActivePromo() && ! $this->hasActiveDiscount()) {
            return $price;
        }

        return $price - ($price * $this->discount);
    }

    public function hasEarnedPromo()
    {
        if ( ! Utils::isNinjaProd() || Utils::isPro()) {
            return false;
        }

        // if they've already had a discount or a promotion is active return false
        if ($this->discount_expires || $this->hasActivePromo()) {
            return false;
        }

        // after 52 weeks, offer a 50% discount for 3 days
        $discounts = [
            52 => [.5, 3],
            16 => [.5, 3],
            10 => [.25, 5],
        ];

        foreach ($discounts as $weeks => $promo) {
            list($discount, $validFor) = $promo;
            $difference = $this->created_at->diffInWeeks();
            if ($difference >= $weeks) {
                $this->discount = $discount;
                $this->promo_expires = date_create()->modify($validFor . ' days')->format('Y-m-d');
                $this->save();
                return true;
            }
        }

        return false;
    }
}
