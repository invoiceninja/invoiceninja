<?php

class Utils
{
	public static function formatPhoneNumber($phoneNumber) 
	{
	    $phoneNumber = preg_replace('/[^0-9]/','',$phoneNumber);

	    if (!$phoneNumber) {
	    	return '';
	    }

	    if(strlen($phoneNumber) > 10) {
	        $countryCode = substr($phoneNumber, 0, strlen($phoneNumber)-10);
	        $areaCode = substr($phoneNumber, -10, 3);
	        $nextThree = substr($phoneNumber, -7, 3);
	        $lastFour = substr($phoneNumber, -4, 4);

	        $phoneNumber = '+'.$countryCode.' ('.$areaCode.') '.$nextThree.'-'.$lastFour;
	    }
	    else if(strlen($phoneNumber) == 10) {
	        $areaCode = substr($phoneNumber, 0, 3);
	        $nextThree = substr($phoneNumber, 3, 3);
	        $lastFour = substr($phoneNumber, 6, 4);

	        $phoneNumber = '('.$areaCode.') '.$nextThree.'-'.$lastFour;
	    }
	    else if(strlen($phoneNumber) == 7) {
	        $nextThree = substr($phoneNumber, 0, 3);
	        $lastFour = substr($phoneNumber, 3, 4);

	        $phoneNumber = $nextThree.'-'.$lastFour;
	    }

	    return $phoneNumber;
	}

	public static function pluralize($string, $count) 
	{
		$string = str_replace('?', $count, $string);
		return $count == 1 ? $string : $string . 's';
	}

	public static function toArray($data)
	{
		return json_decode(json_encode((array) $data), true);
	}

	public static function toSpaceCase($camelStr)
	{
		return preg_replace('/([a-z])([A-Z])/s','$1 $2', $camelStr);
	}

	public static function timestampToDateTimeString($timestamp) {
		$tz = Session::get('tz');
		if (!$tz) {
			$tz = 'US/Eastern';
		}	
		$date = new Carbon($timestamp);	
		$date->tz = $tz;	
		if ($date->year < 1900) {
			return '';
		}
		
		return $date->format('l M jS, Y g:ia');
	}

	public static function timestampToDateString($timestamp) {
		$tz = Session::get('tz');
		if (!$tz) {
			$tz = 'US/Eastern';
		}	
		$date = new Carbon($timestamp);	
		$date->tz = $tz;	
		if ($date->year < 1900) {
			return '';
		}
		return $date->toFormattedDateString();
	}

	public static function toSqlDate($date)
	{
		if (!$date)
		{
			return null;
		}

		return DateTime::createFromFormat('m/d/Y', $date);
	}
	
	public static function fromSqlDate($date)
	{
		if (!$date || $date == '0000-00-00')
		{
			return '';
		}
		
		return DateTime::createFromFormat('Y-m-d', $date)->format('m/d/Y');
	}

	public static function trackViewed($name, $type)
	{
		$url = Request::url();
		$viewed = Session::get(RECENTLY_VIEWED);	
		
		if (!$viewed)
		{
			$viewed = [];
		}

		$object = new stdClass;
		$object->url = $url;
		$object->name = ucwords($type) . ': ' . $name;
		
		for ($i=0; $i<count($viewed); $i++)
		{
			$item = $viewed[$i];
			
			if ($object->url == $item->url)
			{
				array_splice($viewed, $i, 1);
				break;
			}
		}

		array_unshift($viewed, $object);
			
		if (count($viewed) > RECENTLY_VIEWED_LIMIT)
		{
			array_pop($viewed);
		}

		Session::put(RECENTLY_VIEWED, $viewed);
	}

	public static function processVariables($str)
	{
		if (!$str) {
			return '';
		}

		$variables = ['MONTH', 'QUARTER', 'YEAR'];
		for ($i=0; $i<count($variables); $i++)
		{
			$variable = $variables[$i];
			$regExp = '/\[' . $variable . '[+-]?[\d]*\]/';
			preg_match_all($regExp, $str, $matches);
			$matches = $matches[0];
			if (count($matches) == 0) {
				continue;
			}
			foreach ($matches as $match) {
				$offset = 0;
				$addArray = explode('+', $match);
				$minArray = explode('-', $match);
				if (count($addArray) > 1) {
					$offset = intval($addArray[1]);
				} else if (count($minArray) > 1) {
					$offset = intval($minArray[1]) * -1;
				}				

				$val = Utils::getDatePart($variable, $offset);
				$str = str_replace($match, $val, $str);
			}
		}

		return $str;
	}

	private static function getDatePart($part, $offset)
	{
		$offset = intval($offset);
		if ($part == 'MONTH') {
			return Utils::getMonth($offset);
		} else if ($part == 'QUARTER') {
			return Utils::getQuarter($offset);
		} else if ($part == 'YEAR') {
			return Utils::getYear($offset);
		}
	}

	private static function getMonth($offset)
	{
		$months = [ "January", "February", "March", "April", "May", "June",
			"July", "August", "September", "October", "November", "December" ];

		$month = intval(date('n')) - 1;
		$month += $offset;
		$month = $month % 12;
		return $months[$month];
	}

	private static function getQuarter($offset)
	{
		$month = intval(date('n')) - 1;
		$quarter = floor(($month + 3) / 3);
		$quarter += $offset;
    	$quarter = $quarter % 4;
    	if ($quarter == 0) {
         	$quarter = 4;   
    	}
    	return 'Q' . $quarter;
	}

	private static function getYear($offset) 
	{
		$year = intval(date('Y'));
		return $year + $offset;
	}
}