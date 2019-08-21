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

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

class CompanyGateway extends Model
{
    public static $creditCards = 
    	[
            1 => ['card' => 'images/credit_cards/Test-Visa-Icon.png', 'text' => 'Visa'],
            2 => ['card' => 'images/credit_cards/Test-MasterCard-Icon.png', 'text' => 'Master Card'],
            4 => ['card' => 'images/credit_cards/Test-AmericanExpress-Icon.png', 'text' => 'American Express'],
            8 => ['card' => 'images/credit_cards/Test-Diners-Icon.png', 'text' => 'Diners'],
            16 => ['card' => 'images/credit_cards/Test-Discover-Icon.png', 'text' => 'Discover'],
        ];

    public function company()
    {
    	return $this->belongsTo(Company::class);
    }
}
