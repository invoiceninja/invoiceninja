<?php

class Oneoff_Payment_Button_Method implements Payment_Method
{
	private $_params;

	private $_descrip = "Creates HTML code for a button which directs the user to a hosted payments page on which they can complete the transaction.  Similar to Oneoff_Payment.";

	public function __construct()
	{
		$this->_params = array(
			'amt'			=>	'10.00',	//Amount for the payment
			'desc'			=>	'Your button text',
			'notify_url'	=>	'http://notify.me/url',	//Your URL for receiving Instant Payment Notification (IPN) about this transaction. If you do not specify this value in the request, the notification URL from your Merchant Profile is used, if one exists.
			'shipping_amt'  =>	'2.00', //The cost of shipping
			'tax_amt'		=>	'1.00', //Amount for just tax.	
			'continue_url'	=>	'http://continue.after/purchase', //Link for continue shopping button
			'edit_url' => 'http://edit.purchase', //Url for editing one's cart
			'items' => array(
				array(
					'desc' => 'Description for an item',
					'amt' => '3.50',
					'name' => 'The item name',
					'qty' => '2'
				)
			), //An array of items
			'shipping_options' => array(
				array(
					'desc' => 'The shipping option',
					'amt' => '2.00'
				)
			)
		);
	}

	public function get_params()
	{
		return $this->_params;
	}

	public function get_description()
	{
		return $this->_descrip();
	}
}