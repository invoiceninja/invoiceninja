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
 * Class Group
 * @package App\DataMapper
 */
class Group
{
	/**
	 * Name of the group
	 * @var string
	 */
	public $name;

	/**
	 * Group slug			
	 * @var string
	 */
	public $slug;

	/**
	 * Array of data
	 * 
	 * Preferably stored as single dimension array
	 * [1,2,3,4,5,6]
	 * 
	 * @var array
	 */
	public $data;

}