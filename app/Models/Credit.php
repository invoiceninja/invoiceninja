<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Events\CreditWasCreated;
use Laracasts\Presenter\PresentableTrait;

class Credit extends EntityModel
{
    use SoftDeletes;
    use PresentableTrait;

    protected $dates = ['deleted_at'];
    protected $presenter = 'App\Ninja\Presenters\CreditPresenter';

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice')->withTrashed();
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client')->withTrashed();
    }

    public function getName()
    {
        return '';
    }

    public function getEntityType()
    {
        return ENTITY_CREDIT;
    }

    public function apply($amount)
    {
        if ($amount > $this->balance) {
            $applied = $this->balance;
            $this->balance = 0;
        } else {
            $applied = $amount;
            $this->balance = $this->balance - $amount;
        }

        $this->save();

        return $applied;
    }
}

Credit::creating(function ($credit) {

});

Credit::created(function ($credit) {
    event(new CreditWasCreated($credit));
});
