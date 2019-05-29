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

namespace App\Utils\Traits;

/**
 * Class MakesDates
 * @package App\Utils\Traits
 */
trait Inviteable
{

	public function getStatus()
	{
		$status = '';

		if(isset($this->sent_date))
			$status = ctrans('texts.invitation_status_sent');

		if(isset($this->opened_date))
			$status = ctrans('texts.invitation_status_opened');


		if(isset($this->viewed_date))
			$status = ctrans('texts.invitation_status_viewed');


		return $status;
	}

}