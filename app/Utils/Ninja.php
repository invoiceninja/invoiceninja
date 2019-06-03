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

namespace App\Utils;

/**
 * Class Ninja.
 */
class Ninja
{
	public static function isSelfHost()
	{
		return config('ninja.environment') === 'selfhost';
	}

	public static function isHosted()
	{
		return config('ninja.environment') === 'hosted';
	}
}
