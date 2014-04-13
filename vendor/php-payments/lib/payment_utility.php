<?php

class Payment_Utility 
{
	public function __construct(){}

	/*
	* Checks classes which are attempted to autoload, ensures they are not ignored (ie to prevent conflict with frameworks)
	*/
	public static $autoload_ignore = array();

	/**
	 * Autoloader.  Allows us to call classes without a require or include statement - lookups are referred here
	*/
	public function class_autoload($class)
	{
		//If a class name is not going to match, don't bother looking for it - we'll just end up with an exception
		if(strpos($class, 'Payment') === false && strpos($class, 'Driver') === false && strpos($class, 'Method') === false) return;

		//Ignore classes that should be ignored
		foreach(static::$autoload_ignore as $ignore)
		{
			if(strpos($class, $ignore) !== false) return;
		}

		$class = strtolower($class);
		$base_dir = __DIR__.'/';

		if(file_exists($base_dir.$class.'.php'))
		{
			include_once($base_dir.$class.'.php');
			return;
		}
		else if(file_exists($base_dir.'payment_drivers/'.$class.'.php'))
		{
			include_once($base_dir.'payment_drivers/'.$class.'.php');
			return;
		}
		else if(file_exists($base_dir.'payment_methods/'.$class.'.php'))
		{
			include_once($base_dir.'payment_methods/'.$class.'.php');
			return;
		}
		else
		{
			throw new Exception("Could not find class");
		}
	}

	/**
	  * Load a resource.  Alternative to include / require / etc.  Passing a key will return a specific entry in a config / lang array.
	*/
	public static function load($type, $file, $key = null)
	{
		$base_dir = dirname(__DIR__);

		switch($type)
		{
			case $type == 'config':
				$ob = false;
				$path = $base_dir.'/config/'.$file.'.php';
				break;

			case $type == 'file':
				$ob = true;
				$path = $base_dir.'/'.$file.'.php';
				break;
			
			case $type == 'lang':
				$ob = false;
				$path = $base_dir.'/language/'.$file.'_lang.php';
				break;

			default:
				die("$type is not a valid filetype to load for Payments");
		}

		if(!is_file($path)) die("$path does not exist.");

		if($ob)
		{
			ob_start();
				include_once($path);
			return ob_get_clean();	
		}
		else
		{
			$f = include $path;
			return (isset($f[$key])) ? $f[$key] : $f;
		}
	}

	/**
	 * Loads all files in a particular directory
	 *
	 * @param	string	A dir to load from
	*/
	public static function load_all_files($dir)
	{
		$base_dir = dirname(__DIR__);
		foreach(scandir($base_dir.'/'.$dir) as $k=>$v){
			//Ignore swap files, directory files, etc.
			if($v[0] !== '.' && (substr($v, -3, 3) == 'php') )
			{
				$file = str_replace('.php', '', $v);
				self::load('file', $dir.'/'.$file);
			}
		}
	}

	/**
	 * Arrayize an object
	 *
	 * @param	object	the object to convert to an array
	 * @return	array	a converted array
	*/
	public static function arrayize_object($input)
	{
		if(!is_object($input))
		{
			return $input;
		}
		else
		{
			$final = array();
			$vars = get_object_vars($input);
			foreach($vars as $k=>$v)
			{
				if(is_object($v))
				{
					$final[$k] = self::arrayize_object($v);
				}
				else
				{
					$final[$k] = $v;
				}
			}
		}
	
		return $final;
	}

	/**
 	 * Sort an array by an array.  Modified example from StackOverflow: http://stackoverflow.com/questions/348410/sort-an-array-based-on-another-array
	 *
	 * @param	array	An array to sort
	 * @param	array	An array to sort by
	 * @return	array	A sorted array
     */
	public static function sort_array_by_array($array, $order) {
    	$ordered = array();
    	foreach($order as $key) {
       		if(array_key_exists($key,$array)) {
       	   		$ordered[$key] = $array[$key];
                unset($array[$key]);
        	}
    	}

		return $ordered;
	}

	/**
	 * Parses an XML response and creates an object using SimpleXML
	 *
	 * @param 	string	raw xml string
	 * @return	object	response SimpleXMLElement object
	*/		
	public static function parse_xml($xml_str)
	{
		$xml_str = trim($xml_str);
		$xml_str = preg_replace('/xmlns="(.+?)"/', '', $xml_str);
		if($xml_str[0] != '<')
		{
			$xml_str = explode('<', $xml_str);
			if(count($xml_str) > 1)
			{
				unset($xml_str[0]);
				$xml_str = '<'.implode('<', $xml_str);
			}
			else
			{
				$xml_str = $xml_str[0];
			}
		}
	
		try {
			$xml = @new SimpleXMLElement($xml_str);
		}
		catch(Exception $e) {
			return Payment_Response::instance()->local_response(
				'failure',
				'invalid_xml',
				$xml_str
			);
		}

		return $xml;
	}

	/**
	 * Sanitizes XML params so they will not cause parsing errors on remote end
	 *
	 * @param	array	Reference to XML params
	*/
	public static function sanitize_xml_params(&$params)
	{
		if(!function_exists('array_walk_sanitize_callback'))
		{
			function array_walk_sanitize_callback(&$v, $k)
			{
				if(strpos($v, '&') !== false) $v = str_replace('&', '&#x26;', $v);
				if(strpos($v, '<') !== false) $v = str_replace('<', '&#x3c;', $v);
				if(strpos($v, '>') !== false) $v = str_replace('>', '&#x3e;', $v);
			}
		}
		array_walk_recursive($params, 'array_walk_sanitize_callback');
	}
	
	/**
	 * Connection is Secure
	 * 
	 * Checks whether current connection is secure and will redirect
	 * to secure version of page if 'force_secure_connection' is TRUE
	 * 
	 * To Force HTTPS for your entire website, use a .htaccess like the following:
	 *
	 *  RewriteEngine On
	 *  RewriteCond %{SERVER_PORT} 80
	 *  RewriteRule ^(.*)$ https://domain.com/$1 [R,L]
	 * 
	 * @link http://davidwalsh.name/force-secure-ssl-htaccess
	 * @return	bool
	 */
	public static function connection_is_secure($config)
	{
		// Check whether secure connection is required
		if($config['force_secure_connection'] === FALSE) 
		{
			error_log('WARNING!! Using Payment Gateway without Secure Connection!', 0);
			return false;
		}
		
		// Redirect if NOT secure and forcing a secure connection.
		if(($_SERVER['SERVER_PORT'] === '443' && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') === FALSE)
		{
			$loc = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			header($loc);
			exit;
		}
		
		return true;
	}
}
