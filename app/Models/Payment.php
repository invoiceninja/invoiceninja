<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Filterable;
use App\Utils\Number;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;

class Payment extends BaseModel
{
    use MakesHash;
    use Filterable;

    const STATUS_PENDING = 1;
    const STATUS_VOIDED = 2;
    const STATUS_FAILED = 3;
    const STATUS_COMPLETED = 4;
    const STATUS_PARTIALLY_REFUNDED = 5;
    const STATUS_REFUNDED = 6;

    const TYPE_CREDIT_CARD = 1;
    const TYPE_BANK_TRANSFER = 2;
    const TYPE_PAYPAL = 3;
    const TYPE_BITCOIN = 4;
    const TYPE_DWOLLA = 5;
    const TYPE_CUSTOM1 = 6;
    const TYPE_ALIPAY = 7;
    const TYPE_SOFORT = 8;
    const TYPE_SEPA = 9;
    const TYPE_GOCARDLESS = 10;
    const TYPE_APPLE_PAY = 11;
    const TYPE_CUSTOM2 = 12;
    const TYPE_CUSTOM3 = 13;
    const TYPE_TOKEN = 'token';

    protected $fillable = [
		'client_id',
        'payment_type_id',
        'amount',
        'payment_date',
        'transaction_reference'
	];

    protected $casts = [
        'settings' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function invoices()
    {
        return $this->morphedByMany(Invoice::class, 'paymentable');
    }

    public function company_ledger()
    {
        return $this->morphMany(CompanyLedger::class, 'company_ledgerable');
    }

    public function type()
    {
        return $this->hasOne(PaymentType::class,'id','payment_type_id');
    }

    public function formattedAmount()
    {
        return Number::formatMoney($this->amount, $this->client);
    }

    public static function badgeForStatus(int $status)
    {
        switch ($status) {
            case self::STATUS_PENDING:
                return '<h6><span class="badge badge-secondary">'.ctrans('texts.payment_status_1').'</span></h6>';
                break;
            case self::STATUS_VOIDED:
                return '<h6><span class="badge badge-warning">'.ctrans('texts.payment_status_2').'</span></h6>';
                break;
            case self::STATUS_FAILED:
                return '<h6><span class="badge badge-danger">'.ctrans('texts.payment_status_3').'</span></h6>';
                break;
            case self::STATUS_COMPLETED:
                return '<h6><span class="badge badge-info">'.ctrans('texts.payment_status_4').'</span></h6>';
                break;
            case self::STATUS_PARTIALLY_REFUNDED:
                return '<h6><span class="badge badge-success">'.ctrans('texts.payment_status_5').'</span></h6>';
                break;
            case self::STATUS_REFUNDED:
                return '<h6><span class="badge badge-primary">'.ctrans('texts.payment_status_6').'</span></h6>';
                break;         
            default:
                # code...
                break;
        }
    }
}
