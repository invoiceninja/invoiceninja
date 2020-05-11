<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Libraries\Currency\Conversion;

use App\Models\Currency;
use Illuminate\Support\Carbon;

class CurrencyApi implements CurrencyConversionInterface
{

	public function convert($amount, $from_currency_id, $to_currency_id, $date = null)
	{

		if(!$date)
			$date = Carbon::now();

		$from_currency = Currency::find($from_currency_id)->code;

		$to_currency = Currency::find($to_currency_id);

		$currency = new \danielme85\CConverter\Currency();

	    return $currency->convert($from_currency, $to_currency->code, $amount, $to_currency->precision, $date);
		
	}

	public function exchangeRate($from_currency_id, $to_currency_id, $date = null)
	{
		
		if(!$date)
			$date = Carbon::now();

		$from_currency = Currency::find($from_currency_id)->code;

		$to_currency = Currency::find($to_currency_id);

		return \danielme85\CConverter\Currency::conv($from_currency, $to_currency->code, 1, $to_currency->precision, $date);

	}

}