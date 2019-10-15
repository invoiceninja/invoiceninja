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

namespace App\Helpers\Invoice;

use App\Helpers\Invoice\InvoiceItemSum;
use Illuminate\Support\Collection;

class InvoiceSum
{
	protected $invoice;

	protected $settings;

	public $tax_map;

	public $invoice_item;

	/**
	 * Constructs the object with Invoice and Settings object
	 *
	 * @param      \App\Models\Invoice  $invoice   The invoice
	 */
	public function __construct($invoice, $settings)
	{
		
		$this->invoice = $invoice;

		$this->settings = $settings;

		$this->tax_map = new Collection;

	}

	public function build()
	{
		$this->calculateLineItems()
			 ->calculateDiscount();

		return $this;
	}

	private function calculateLineItems()
	{
		$this->invoice_items = new InvoiceItemSum($this->invoice, $this->settings);
		$this->invoice_items->process();

		return $this;
	}

	private function calculateDiscount()
	{
		$this->invoice_items->applyInvoiceDiscount($this->invoice);
	}

	public function getInvoice()
	{
		return $this->invoice;
	}
}