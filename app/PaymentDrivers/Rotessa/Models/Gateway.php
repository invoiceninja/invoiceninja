<?php

namespace App\PaymentDrivers\Rotessa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Gateway as BaseGateway;

class Gateway extends BaseGateway
{

    public function getOptionsAttribute()
    {
        $gateway_types = config('rotessa.gateway_types');
        $options = parent::getOptionsAttribute();
        if($this->name == 'Rotessa' && empty($options)) {
            $options  = $gateway_types;
        }

        return $options;
    }
}

