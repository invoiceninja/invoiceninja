<?php

class Payment_Request
{
	public function __construct(){}

	/**
	 * Returns an xml document
	 *
	 * @param 	array	the structure for the xml
	 * @return	string	a well-formed XML string
	*/	
	public static function build_xml_request($xml_version, $character_encoding, $xml_params, $parent = NULL, $xml_schema = NULL, $xml_extra = NULL)
	{
		$encoding = ($character_encoding !== null) ? ' encoding="'.$character_encoding.'"' : '';
		$xml = '<?xml version="'.$xml_version.'"'.$encoding.'?>';

		if(!is_null($xml_extra))
		{
			$xml .= '<?'.$xml_extra.'?>';
		}
		
		if(!is_null($parent) AND is_null($xml_schema))
		{
			$xml .= '<'.$parent.'>';
		}
				
		if(!is_null($parent) AND !is_null($xml_schema))
		{
			$xml .= '<'.$parent.' '.$xml_schema.'>';
		}
		
		//XML parsing at the server end will break if certain characters are not replaced
		Payment_Utility::sanitize_xml_params($xml_params);

		$xml .= self::build_nodes($xml_params);
		
		if(!is_null($parent))
		{
			$xml .= '</'.$parent.'>';
		}
		
		return $xml;
	}

	/**
	 * Returns a well-formed string of XML nodes
	 *
	 * @param	array	associative array of values
	 * @return	string	well-formed XML string
	*/
	public static function build_nodes($params, $key_to_set = NULL)
	{
		$string = "";
		$dont_wrap = FALSE;
		
		foreach($params as $k=>$v)
		{		
			if(is_bool($v) AND $v === TRUE)
			{
				$v = 'true';
			}
			
			if(is_bool($v) AND $v === FALSE)
			{
				$v = 'false';
			}
			
			if(empty($v) AND $v != '0')
			{
				unset($k);
				continue;
			}
			
			if(is_array($v))
			{		
				if($k === 'repeated_key')
				{					
					if($v['wraps'] === FALSE)
					{
						$dont_wrap = TRUE;
					}
					
					$node_name = $v['name'];
					$node_contents = self::build_nodes($v['values'], $v['name']);
				}
				else
				{
					$node_name = $k;
					$node_contents = self::build_nodes($v);
				}
			}
			
			if(!is_array($v))
			{
				$node_name = $k;
				$node_contents = $v;
			}
			
			if($key_to_set !== NULL)
			{
				$node_name = $key_to_set;
			}
				
			if(!empty($node_contents) AND $dont_wrap === TRUE)
			{
				$string .= $node_contents;
			}

			if(!empty($node_contents) AND $dont_wrap === FALSE)
			{		
				$string .= '<'.$node_name.'>';
				$string .= $node_contents;
				$string .= '</'.$node_name.'>';
			}	
			
			if($node_contents === '0' AND $dont_wrap === TRUE)
			{
				$string .= $node_contents;			
			}

			if($node_contents === '0' AND $dont_wrap === FALSE)
			{
				$string .= '<'.$node_name.'>';
				$string .= $node_contents;
				$string .= '</'.$node_name.'>';			
			}					
		}
		return $string;
	}

	/**
	 * Makes the actual request to the gateway
	 *
	* @param   string  This is the API endpoint currently being used
    * @param  string  The data to be passed to the API
    * @param  string  A specific content type to define for cURL request
	* @return	object	response object
	*/	
	public static function curl_request($query_string, $payload = NULL, $content_type = NULL, $custom_headers = NULL)
	{
		$headers = (is_null($custom_headers)) ? array() : $custom_headers;
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $query_string);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, 0);

		if(is_null($payload))
		{
			$request = curl_exec($curl);
			if($request[0] == '<')
			{
				return Payment_Utility::parse_xml($request);
			}
			else
			{
				return $request;
			}
		}
		else
		{
			if(is_null($content_type))
			{
				$xml = TRUE;
				$headers[] = "Content-Type: text/xml";
			}
			else
			{
				if(strpos($content_type, 'xml') !== FALSE)
				{
					$xml = TRUE;
				}
				
				$headers[] = "Content-Type: $content_type";
			}
			
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);

			$request = curl_exec($curl);

			if(isset($xml) && $xml === TRUE)
			{
				return Payment_Utility::parse_xml($request);
			}
			else
			{
				return $request;
			}
		}
	}		
}
