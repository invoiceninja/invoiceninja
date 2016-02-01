<?php namespace App\Models;

use Laracasts\Presenter\PresentableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Events\ExpenseWasCreated;
use App\Events\ExpenseWasUpdated;
use App\Events\ExpenseWasDeleted;

class Expense extends EntityModel
{
    // Expenses
    use SoftDeletes;
    use PresentableTrait;

    protected $dates = ['deleted_at','expense_date'];
    protected $presenter = 'App\Ninja\Presenters\ExpensePresenter';

    protected $fillable = [
        'client_id',
        'vendor_id',
        'expense_currency_id',
        'invoice_currency_id',
        'amount',
        'foreign_amount',
        'exchange_rate',
        'private_notes',
        'public_notes',
        'bank_id',
        'transaction_id',
    ];
    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function vendor()
    {
        return $this->belongsTo('App\Models\Vendor')->withTrashed();
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client')->withTrashed();
    }

    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice')->withTrashed();
    }

    public function getName()
    {
        if($this->expense_number)
            return $this->expense_number;

        return $this->public_id;
    }

    public function getDisplayName()
    {
        return $this->getName();
    }

    public function getRoute()
    {
        return "/expenses/{$this->public_id}";
    }

    public function getEntityType()
    {
        return ENTITY_EXPENSE;
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

Expense::creating(function ($expense) {
    $expense->setNullValues();
});

Expense::created(function ($expense) {
    event(new ExpenseWasCreated($expense));
});

Expense::updating(function ($expense) {
    $expense->setNullValues();
});

Expense::updated(function ($expense) {
    event(new ExpenseWasUpdated($expense));
});

Expense::deleting(function ($expense) {
    $expense->setNullValues();
});

Expense::deleted(function ($expense) {
    event(new ExpenseWasDeleted($expense));
});
