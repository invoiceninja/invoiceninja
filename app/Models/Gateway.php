<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Omnipay\Omnipay;

class Gateway extends StaticModel
{
    protected $casts = [
        'is_offsite' => 'boolean',
        'is_secure' => 'boolean',
        'recommended' => 'boolean',
        //'visible' => 'boolean',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'default_gateway_type_id' => 'string',
        'fields' => 'json',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * @return mixed
     */
    public function getFields()
    {
        if ($this->isCustom()) {
            return [
                'name' => '',
                'text' => '',
            ];
        } else {
            return Omnipay::create($this->provider)->getDefaultParameters();
        }
    }

    /**
     * Test if gateway is custom.
     * @return bool TRUE|FALSE
     */
    public function isCustom() :bool
    {
        return in_array($this->id, [62, 67, 68]); //static table ids of the custom gateways
    }
}
