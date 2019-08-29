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

namespace App\Models\Presenters;

/**
 * Class InvoicePresenter
 *
 * For convenience and to allow users to easiliy 
 * customise their invoices, we provide all possible
 * invoice variables to be available from this presenter.
 *
 * Shortcuts to other presenters are here to facilitate 
 * a clean UI / UX
 * 
 * @package App\Models\Presenters
 */
class InvoicePresenter extends EntityPresenter
{

	public function clientName()
	{
		return $this->client->present()->name();
	}

	public function address()
	{
		return $this->client->present()->address();
	}

	pubic function shipping_address()
	{
		return $this->client->present()->shipping_address();
	}

	public function company_logo()
	{
		return $this->company->logo;
	}

	public function client_logo()
	{
		return $this->client->logo;
	}

	public function company_name()
	{
		return $this->company->present()->name()
	}

	public function company_address()
	{
		return $this->company->present()->address();
	}
}
