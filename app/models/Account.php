<?php

class Account extends Eloquent
{
	protected $softDelete = true;

	public function users()
	{
		return $this->hasMany('User');
	}

	public function clients()
	{
		return $this->hasMany('Client');
	}

	public function account_gateways()
	{
		return $this->hasMany('AccountGateway');
	}

	public function isGatewayConfigured($gatewayId = 0)
	{
		if ($gatewayId)
		{
			return $this->getGatewayConfig($gatewayId) != false;
		}
		else
		{
			return count($this->account_gateways) > 0;
		}
	}

	public function getGatewayConfig($gatewayId)
	{
		foreach ($this->account_gateways as $gateway)
		{
			if ($gateway->gateway_id == $gatewayId)
			{
				return $gateway;
			}
		}	

		return false;	
	}

	public function getLogoPath()
	{
		return 'logo/' . $this->key . '.jpg';
	}

	public function getLogoWidth()
	{
		list($width, $height) = getimagesize($this->getLogoPath());
		return $width;
	}

	public function getLogoHeight()
	{
		list($width, $height) = getimagesize($this->getLogoPath());
		return $height;	
	}
}