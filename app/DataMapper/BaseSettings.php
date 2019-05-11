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

namespace App\DataMapper;

/**
 * ClientSettings
 */
class BaseSettings
{

	public function __construct($obj)
	{
		foreach($obj as $key => $value)
			$this->{$key} = $value;
	}
	

}