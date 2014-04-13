<?php

class Google_Checkout_Driver extends Payment_Driver
{
	/**
	 * The Config Settings
	*/
	private $_config;

	/**
	 * The API Mode (sandbox or live)
	*/
	private $_mode;

	/**
	 * If it's a button, we just need to spit the button out, we don't need to parse a response
	*/
	private $_is_button;

	/**
	 * Constructor Function
	*/
	public function __construct($config)
	{
		$this->_mode = ($config['mode'] == 'test') ? 'sandbox' : 'live';

		$this->_config = $config;
	}

	/**
	 * The Caller Magic Method
	*/
	public function __call($method, $params)
	{
		Payment_Utility::load_all_files('vendor/google_checkout/library');
		$this->_lib_method = $method;
		$args = $params[0];

		$request = $this->_build_request($args);

		return $this->_parse_response($request);
	}

	/**
	 * Maps PHP Payments to Gateway
	*/
	public function method_map()
	{
		$map = array(
			'oneoff_payment_button' => array(
				'api' => 'GoogleCart',
				'required' => array(
					'currency_code',
					'items',
					'shipping_options',
					/*'items' => array( I'll THINK about adding item support = )
						'name',
						'desc',
						'qty',
						'amt',
					),
					'shipping_options' => array(
						'option' => array(
							'desc',
							'amt'
						)
					),*/
					'edit_url',
					'continue_url'
				),
				'static' => array(
					'SetRequestBuyerPhone' => $this->_config['request_buyer_phone'],
					'CheckoutButtonCode' => $this->_config['button_size']
				)
			)
		);

		return $map;
	}

	/**
	 * Builds a Request 
	*/
	protected function _build_request($params)
	{
		$map = $this->method_map();
		$l = $this->_lib_method;
		$api = $map[$l]['api']; //Creates the object that the request is made from

		$caller = new $api($this->_config['merchant_id'], $this->_config['merchant_key'], $this->_mode, $params['currency_code']);

		foreach($params as $k=>$v)
		{
			if($k == 'items'){
				foreach($v as $item)
				{
					$item_arr = new GoogleItem(
						$item['name'],
						$item['desc'],
						$item['qty'],
						$item['amt']
					);
					$caller->AddItem($item_arr);
				}
			}

			if($k =='shipping_options'){
				foreach($v as $ship)
				{	
					$ship_arr = new GoogleFlatRateShipping(
						$ship['desc'],
						$ship['amt']
					);

					//TODO: Add filters

					$caller->AddShipping($ship_arr);
				}
			}

			//TODO: Add tax rules

			if($k == 'phone')
			{
				$caller->SetRequestBuyerPhone(true);
			}
		}

		if($api = 'GoogleCart')
		{
			$caller->SetEditCartUrl($params['edit_url']);
			$caller->SetContinueShoppingUrl($params['continue_url']);

			$this->_is_button = TRUE;

			return $caller->CheckoutButtonCode($this->_config['button_size']);
		}
	}

	/**
	 * Parses Response from the Gateway
	*/
	protected function _parse_response($response)
	{
		if($this->_is_button)
		{
			return Payment_Response::instance()->local_response(
				'success',
				$this->_lib_method.'_success',
				$response
			);
		}
	}
}