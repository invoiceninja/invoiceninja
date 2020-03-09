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

namespace App\Designs;

class Custom extends AbstractDesign
{
	public $includes;

	public $header;

	public $body;
	
	public $product;
	
	public $task;

	public $footer;
	
	public $name;

	public function __construct($design) 
	{
		$this->name = $design->name;

		$this->includes = $design->design->includes;

		$this->header = $design->design->header;
		
		$this->body = $design->design->body;
		
		$this->product = $design->design->product;
		
		$this->task = $design->design->task;

		$this->footer = $design->design->footer;
			
	}

	public function name()
	{
		return $this->name;
	}

	public function includes()
	{
		return $this->includes;
	}

	public function header() 
	{

		return $this->header;
			
	}

	public function body() 
	{

		return $this->body;	

	}

	public function product() 
	{

		return $this->product;

	}

	public function task()
	{
		return $this->task;
	}

	public function footer() 
	{

		return $this->footer;
		
	}

}