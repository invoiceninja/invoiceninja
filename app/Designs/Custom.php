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
	private $include;

	private $header;

	private $body;
	
	private $table;
	
	private $footer;
	
	private $table_styles;

	public function __construct($design) 
	{
		$this->include = $design->include;

		$this->header = $design->header;
		
		$this->body = $design->body;
		
		$this->table = $design->table;
		
		$this->footer = $design->footer;
		
		$this->table_styles = $design->table_styles;
	
	}

	public function include()
	{
		return $this->include;
	}

	public function header() 
	{

		return $this->header;
			
	}

	public function body() 
	{

		return $this->body;	

	}

	public function table_styles() 
	{

		return $this->table_styles;

	}

	public function table() 
	{

		return $this->table;

	}

	public function footer() 
	{

		return $this->footer;

	}

}