<?php namespace App\Models;

use Cache;
use Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends EntityModel
{
    use SoftDeletes;

    public $timestamps = true;

    protected $dates = ['deleted_at'];
    protected $hidden = ['id'];

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function contact()
    {
        return $this->belongsTo('App\Models\Contact');
    }

    public function account_gateway_token()
    {
        return $this->belongsTo('App\Models\AccountGatewayToken');
    }

    public function payment_type()
    {
        return $this->belongsTo('App\Models\PaymentType');
    }

    public function currency()
    {
        return $this->belongsTo('App\Models\Currency');
    }

    public function payments()
    {
        return $this->hasMany('App\Models\Payments');
    }

    public function getBankDataAttribute()
    {
        if (!$this->routing_number) {
            return null;
        }
        return static::lookupBankData($this->routing_number);
    }

    public function getBankNameAttribute($bank_name)
    {
        if ($bank_name) {
            return $bank_name;
        }
        $bankData = $this->bank_data;

        return $bankData?$bankData->name:null;
    }

    public function getLast4Attribute($value)
    {
        return $value ? str_pad($value, 4, '0', STR_PAD_LEFT) : null;
    }

    public function scopeClientId($query, $clientId)
    {
        return $query->with(['contact' => function($query) use ($clientId) {
            return $query->whereClientId($clientId);
        }]);
    }

    public function scopeIsBankAccount($query, $isBank)
    {
        if ($isBank) {
            $query->where('payment_type_id', '=', PAYMENT_TYPE_ACH);
        } else {
            $query->where('payment_type_id', '!=', PAYMENT_TYPE_ACH);
        }
    }

    public function imageUrl()
    {
        return url(sprintf('/images/credit_cards/%s.png', str_replace(' ', '', strtolower($this->payment_type->name))));
    }

    public static function lookupBankData($routingNumber) {
        $cached = Cache::get('bankData:'.$routingNumber);

        if ($cached != null) {
            return $cached == false ? null : $cached;
        }

        $dataPath = base_path('vendor/gatepay/FedACHdir/FedACHdir.txt');

        if (!file_exists($dataPath) || !$size = filesize($dataPath)) {
            return 'Invalid data file';
        }

        $lineSize = 157;
        $numLines = $size/$lineSize;

        if ($numLines % 1 != 0) {
            // The number of lines should be an integer
            return 'Invalid data file';
        }

        // Format: http://www.sco.ca.gov/Files-21C/Bank_Master_Interface_Information_Package.pdf
        $file = fopen($dataPath, 'r');

        // Binary search
        $low = 0;
        $high = $numLines - 1;
        while ($low <= $high) {
            $mid = floor(($low + $high) / 2);

            fseek($file, $mid * $lineSize);
            $thisNumber = fread($file, 9);

            if ($thisNumber > $routingNumber) {
                $high = $mid - 1;
            } else if ($thisNumber < $routingNumber) {
                $low = $mid + 1;
            } else {
                $data = new \stdClass();
                $data->routing_number = $thisNumber;

                fseek($file, 26, SEEK_CUR);

                $data->name = trim(fread($file, 36));
                $data->address = trim(fread($file, 36));
                $data->city = trim(fread($file, 20));
                $data->state = fread($file, 2);
                $data->zip = fread($file, 5).'-'.fread($file, 4);
                $data->phone = fread($file, 10);
                break;
            }
        }

        if (!empty($data)) {
            Cache::put('bankData:'.$routingNumber, $data, 5);
            return $data;
        } else {
            Cache::put('bankData:'.$routingNumber, false, 5);
            return null;
        }
    }

    public function requiresDelayedAutoBill(){
        return $this->payment_type_id == PAYMENT_TYPE_ACH;
    }
}

PaymentMethod::deleting(function($paymentMethod) {
    $accountGatewayToken = $paymentMethod->account_gateway_token;
    if ($accountGatewayToken->default_payment_method_id == $paymentMethod->id) {
        $newDefault = $accountGatewayToken->payment_methods->first(function($i, $paymentMethdod) use ($accountGatewayToken){
            return $paymentMethdod->id != $accountGatewayToken->default_payment_method_id;
        });
        $accountGatewayToken->default_payment_method_id = $newDefault ? $newDefault->id : null;
        $accountGatewayToken->save();
    }
});
