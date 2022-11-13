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
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankTransactionRule extends BaseModel
{
    use SoftDeletes;
    use MakesHash;
    use Filterable;
    
    protected $fillable = [
        'name',
        'rules',
        'auto_convert',
        'matches_on_all',
        'applies_to',
        'record_as',
        'client_id',
        'vendor_id',
        'category_id',
    ];

    protected $dates = [
    ];
    
    /* Columns to search */
    protected array $search_keys = [
        'client_id' => 'client',
        'vendor_id' => 'vendor',
        'description' => 'description',
        'transaction_reference' => 'transaction_reference',
        'amount' => 'amount',
    ];

    /* Amount */
    protected array $number_operators = [
        '=',
        '>',
        '>=',
        '<',
        '<='
    ];

    /* Description, Client, Vendor, Reference Number */
    protected array $string_operators = [
        'is',
        'contains',
        'starts_with',
        'is_empty',
    ];

    public function getEntityType()
    {
        return self::class;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function expense_cateogry()
    {
        return $this->belongsTo(ExpenseCategory::class)->withTrashed();
    }

}