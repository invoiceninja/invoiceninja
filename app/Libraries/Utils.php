<?php namespace App\Libraries;

use Auth;
use Cache;
use DB;
use App;
use Schema;
use Session;
use Request;
use View;
use DateTimeZone;
use Input;
use Log;
use DateTime;
use stdClass;
use Carbon;

use App\Models\Currency;

class Utils
{
    public static function isRegistered()
    {
        return Auth::check() && Auth::user()->registered;
    }

    public static function isConfirmed()
    {
        return Auth::check() && Auth::user()->confirmed;
    }

    public static function isDatabaseSetup()
    {
        try {
            if (Schema::hasTable('accounts')) {
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function isProd()
    {
        return App::environment() == ENV_PRODUCTION;
    }

    public static function isNinja()
    {
        return self::isNinjaProd() || self::isNinjaDev();
    }

    public static function isNinjaProd()
    {
        return isset($_ENV['NINJA_PROD']) && $_ENV['NINJA_PROD'] == 'true';
    }

    public static function isNinjaDev()
    {
        return isset($_ENV['NINJA_DEV']) && $_ENV['NINJA_DEV'] == 'true';
    }

    public static function allowNewAccounts()
    {
        return Utils::isNinja() || Auth::check();
    }

    public static function isPro()
    {
        return Auth::check() && Auth::user()->isPro();
    }

    public static function isEnglish()
    {
        return App::getLocale() == 'en';
    }

    public static function getUserType()
    {
        if (Utils::isNinja()) {
            return USER_TYPE_CLOUD_HOST;
        } else {
            return USER_TYPE_SELF_HOST;
        }
    }

    public static function getDemoAccountId()
    {
        return isset($_ENV[DEMO_ACCOUNT_ID]) ? $_ENV[DEMO_ACCOUNT_ID] : false;
    }

    public static function isDemo()
    {
        return Auth::check() && Auth::user()->isDemo();
    }

    public static function getNewsFeedResponse($userType = false)
    {
        if (!$userType) {
            $userType = Utils::getUserType();
        }

        $response = new stdClass();
        $response->message = isset($_ENV["{$userType}_MESSAGE"]) ? $_ENV["{$userType}_MESSAGE"] : '';
        $response->id = isset($_ENV["{$userType}_ID"]) ? $_ENV["{$userType}_ID"] : '';
        $response->version = NINJA_VERSION;

        return $response;
    }

    public static function getProLabel($feature)
    {
        if (Auth::check()
                && !Auth::user()->isPro()
                && $feature == ACCOUNT_ADVANCED_SETTINGS) {
            return '&nbsp;<sup class="pro-label">PRO</sup>';
        } else {
            return '';
        }
    }

    public static function basePath()
    {
        return substr($_SERVER['SCRIPT_NAME'], 0, strrpos($_SERVER['SCRIPT_NAME'], '/') + 1);
    }

    public static function trans($input)
    {
        $data = [];

        foreach ($input as $field) {
            if ($field == "checkbox") {
                $data[] = $field;
            } else {
                $data[] = trans("texts.$field");
            }
        }

        return $data;
    }

    public static function fatalError($message = false, $exception = false)
    {
        if (!$message) {
            $message = "An error occurred, please try again later.";
        }

        static::logError($message.' '.$exception);

        $data = [
            'showBreadcrumbs' => false,
            'hideHeader' => true,
        ];

        return View::make('error', $data)->with('error', $message);
    }

    public static function getErrorString($exception)
    {
        $class = get_class($exception);
        $code = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : $exception->getCode();
        return  "***{$class}*** [{$code}] : {$exception->getFile()} [Line {$exception->getLine()}] => {$exception->getMessage()}";
    }

    public static function logError($error, $context = 'PHP')
    {
        $count = Session::get('error_count', 0);
        Session::put('error_count', ++$count);
        if ($count > 100) {
            return 'logged';
        }

        $data = [
            'context' => $context,
            'user_id' => Auth::check() ? Auth::user()->id : 0,
            'user_name' => Auth::check() ? Auth::user()->getDisplayName() : '',
            'url' => Input::get('url', Request::url()),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'ip' => Request::getClientIp(),
            'count' => Session::get('error_count', 0),
            //'input' => Input::all()
        ];

        Log::error($error."\n", $data);

        /*
        Mail::queue('emails.error', ['message'=>$error.' '.json_encode($data)], function($message)
        {
            $message->to($email)->subject($subject);
        });
        */
    }

    public static function parseFloat($value)
    {
        $value = preg_replace('/[^0-9\.\-]/', '', $value);

        return floatval($value);
    }

    public static function formatPhoneNumber($phoneNumber)
    {
        $phoneNumber = preg_replace('/[^0-9a-zA-Z]/', '', $phoneNumber);

        if (!$phoneNumber) {
            return '';
        }

        if (strlen($phoneNumber) > 10) {
            $countryCode = substr($phoneNumber, 0, strlen($phoneNumber)-10);
            $areaCode = substr($phoneNumber, -10, 3);
            $nextThree = substr($phoneNumber, -7, 3);
            $lastFour = substr($phoneNumber, -4, 4);

            $phoneNumber = '+'.$countryCode.' ('.$areaCode.') '.$nextThree.'-'.$lastFour;
        } elseif (strlen($phoneNumber) == 10 && in_array(substr($phoneNumber, 0, 3), array(653, 656, 658, 659))) {
            /**
             * SG country code are 653, 656, 658, 659
             * US area code consist of 650, 651 and 657
             * @see http://en.wikipedia.org/wiki/Telephone_numbers_in_Singapore#Numbering_plan
             * @see http://www.bennetyee.org/ucsd-pages/area.html
             */
            $countryCode = substr($phoneNumber, 0, 2);
            $nextFour = substr($phoneNumber, 2, 4);
            $lastFour = substr($phoneNumber, 6, 4);

            $phoneNumber = '+'.$countryCode.' '.$nextFour.' '.$lastFour;
        } elseif (strlen($phoneNumber) == 10) {
            $areaCode = substr($phoneNumber, 0, 3);
            $nextThree = substr($phoneNumber, 3, 3);
            $lastFour = substr($phoneNumber, 6, 4);

            $phoneNumber = '('.$areaCode.') '.$nextThree.'-'.$lastFour;
        } elseif (strlen($phoneNumber) == 7) {
            $nextThree = substr($phoneNumber, 0, 3);
            $lastFour = substr($phoneNumber, 3, 4);

            $phoneNumber = $nextThree.'-'.$lastFour;
        }

        return $phoneNumber;
    }

    public static function formatMoney($value, $currencyId = false)
    {
        if (!$currencyId) {
            $currencyId = Session::get(SESSION_CURRENCY, DEFAULT_CURRENCY);
        }

        foreach (Cache::get('currencies') as $currency) {
            if ($currency->id == $currencyId) {
                break;
            }
        }

        if (!$currency) {
            $currency = Currency::find(1);
        }

        if (!$value) {
            $value = 0;
        }

        Cache::add('currency', $currency, DEFAULT_QUERY_CACHE);

        return $currency->symbol.number_format($value, $currency->precision, $currency->decimal_separator, $currency->thousand_separator);
    }

    public static function pluralize($string, $count)
    {
        $field = $count == 1 ? $string : $string.'s';
        $string = trans("texts.$field", ['count' => $count]);

        return $string;
    }

    public static function toArray($data)
    {
        return json_decode(json_encode((array) $data), true);
    }

    public static function toSpaceCase($camelStr)
    {
        return preg_replace('/([a-z])([A-Z])/s', '$1 $2', $camelStr);
    }

    public static function timestampToDateTimeString($timestamp)
    {
        $timezone = Session::get(SESSION_TIMEZONE, DEFAULT_TIMEZONE);
        $format = Session::get(SESSION_DATETIME_FORMAT, DEFAULT_DATETIME_FORMAT);

        return Utils::timestampToString($timestamp, $timezone, $format);
    }

    public static function timestampToDateString($timestamp)
    {
        $timezone = Session::get(SESSION_TIMEZONE, DEFAULT_TIMEZONE);
        $format = Session::get(SESSION_DATE_FORMAT, DEFAULT_DATE_FORMAT);

        return Utils::timestampToString($timestamp, $timezone, $format);
    }

    public static function dateToString($date)
    {
        $dateTime = new DateTime($date);
        $timestamp = $dateTime->getTimestamp();
        $format = Session::get(SESSION_DATE_FORMAT, DEFAULT_DATE_FORMAT);

        return Utils::timestampToString($timestamp, false, $format);
    }

    public static function timestampToString($timestamp, $timezone = false, $format)
    {
        if (!$timestamp) {
            return '';
        }
        $date = Carbon::createFromTimeStamp($timestamp);
        if ($timezone) {
            $date->tz = $timezone;
        }
        if ($date->year < 1900) {
            return '';
        }

        return $date->format($format);
    }

    public static function getTiemstampOffset()
    {
        $timezone = new DateTimeZone(Session::get(SESSION_TIMEZONE, DEFAULT_TIMEZONE));
        $datetime = new DateTime('now', $timezone);
        $offset = $timezone->getOffset($datetime);
        $minutes = $offset / 60;

        return $minutes;
    }

    public static function toSqlDate($date, $formatResult = true)
    {
        if (!$date) {
            return;
        }

        $format = Session::get(SESSION_DATE_FORMAT, DEFAULT_DATE_FORMAT);
        $dateTime = DateTime::createFromFormat($format, $date);

        return $formatResult ? $dateTime->format('Y-m-d') : $dateTime;
    }

    public static function fromSqlDate($date, $formatResult = true)
    {
        if (!$date || $date == '0000-00-00') {
            return '';
        }

        $format = Session::get(SESSION_DATE_FORMAT, DEFAULT_DATE_FORMAT);
        $dateTime = DateTime::createFromFormat('Y-m-d', $date);

        return $formatResult ? $dateTime->format($format) : $dateTime;
    }

    public static function fromSqlDateTime($date, $formatResult = true)
    {
        if (!$date || $date == '0000-00-00 00:00:00') {
            return '';
        }

        $timezone = Session::get(SESSION_TIMEZONE, DEFAULT_TIMEZONE);
        $format = Session::get(SESSION_DATETIME_FORMAT, DEFAULT_DATETIME_FORMAT);

        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $date);
        $dateTime->setTimeZone(new DateTimeZone($timezone));

        return $formatResult ? $dateTime->format($format) : $dateTime;
    }

    public static function today($formatResult = true)
    {
        $timezone = Session::get(SESSION_TIMEZONE, DEFAULT_TIMEZONE);
        $format = Session::get(SESSION_DATE_FORMAT, DEFAULT_DATE_FORMAT);

        $date = date_create(null, new DateTimeZone($timezone));

        if ($formatResult) {
            return $date->format($format);
        } else {
            return $date;
        }
    }

    public static function trackViewed($name, $type, $url = false)
    {
        if (!$url) {
            $url = Request::url();
        }

        $viewed = Session::get(RECENTLY_VIEWED);

        if (!$viewed) {
            $viewed = [];
        }

        $object = new stdClass();
        $object->accountId = Auth::user()->account_id;
        $object->url = $url;
        $object->name = ucwords($type).': '.$name;

        $data = [];
        $counts = [];

        for ($i = 0; $i<count($viewed); $i++) {
            $item = $viewed[$i];

            if ($object->url == $item->url || $object->name == $item->name) {
                continue;
            }

            // temporary fix to check for new property in session
            if (!property_exists($item, 'accountId')) {
                continue;
            }

            array_unshift($data, $item);
            if (isset($counts[$item->accountId])) {
                $counts[$item->accountId]++;
            } else {
                $counts[$item->accountId] = 1;
            }
        }

        array_unshift($data, $object);
        
        if (isset($counts[Auth::user()->account_id]) && $counts[Auth::user()->account_id] > RECENTLY_VIEWED_LIMIT) {
            array_pop($data);
        }

        Session::put(RECENTLY_VIEWED, $data);
    }

    public static function processVariables($str)
    {
        if (!$str) {
            return '';
        }

        $variables = ['MONTH', 'QUARTER', 'YEAR'];
        for ($i = 0; $i<count($variables); $i++) {
            $variable = $variables[$i];
            $regExp = '/:'.$variable.'[+-]?[\d]*/';
            preg_match_all($regExp, $str, $matches);
            $matches = $matches[0];
            if (count($matches) == 0) {
                continue;
            }
            usort($matches, function($a, $b) {
                return strlen($b) - strlen($a);
            });
            foreach ($matches as $match) {
                $offset = 0;
                $addArray = explode('+', $match);
                $minArray = explode('-', $match);
                if (count($addArray) > 1) {
                    $offset = intval($addArray[1]);
                } elseif (count($minArray) > 1) {
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
        } elseif ($part == 'QUARTER') {
            return Utils::getQuarter($offset);
        } elseif ($part == 'YEAR') {
            return Utils::getYear($offset);
        }
    }

    private static function getMonth($offset)
    {
        $months = [ "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December", ];

        $month = intval(date('n')) - 1;

        $month += $offset;
        $month = $month % 12;

        if ($month < 0) {
            $month += 12;
        }

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

        return 'Q'.$quarter;
    }

    private static function getYear($offset)
    {
        $year = intval(date('Y'));

        return $year + $offset;
    }

    public static function getEntityName($entityType)
    {
        return ucwords(str_replace('_', ' ', $entityType));
    }

    public static function getClientDisplayName($model)
    {
        if ($model->client_name) {
            return $model->client_name;
        } elseif ($model->first_name || $model->last_name) {
            return $model->first_name.' '.$model->last_name;
        } else {
            return $model->email;
        }
    }

    public static function encodeActivity($person = null, $action, $entity = null, $otherPerson = null)
    {
        $person = $person ? $person->getDisplayName() : '<i>System</i>';
        $entity = $entity ? $entity->getActivityKey() : '';
        $otherPerson = $otherPerson ? 'to '.$otherPerson->getDisplayName() : '';
        $token = Session::get('token_id') ? ' ('.trans('texts.token').')' : '';

        return trim("$person $token $action $entity $otherPerson");
    }

    public static function decodeActivity($message)
    {
        $pattern = '/\[([\w]*):([\d]*):(.*)\]/i';
        preg_match($pattern, $message, $matches);

        if (count($matches) > 0) {
            $match = $matches[0];
            $type = $matches[1];
            $publicId = $matches[2];
            $name = $matches[3];

            $link = link_to($type.'s/'.$publicId, $name);
            $message = str_replace($match, "$type $link", $message);
        }

        return $message;
    }

    public static function generateLicense()
    {
        $parts = [];
        for ($i = 0; $i<5; $i++) {
            $parts[] = strtoupper(str_random(4));
        }

        return implode('-', $parts);
    }

    public static function lookupEventId($eventName)
    {
        if ($eventName == 'create_client') {
            return EVENT_CREATE_CLIENT;
        } elseif ($eventName == 'create_invoice') {
            return EVENT_CREATE_INVOICE;
        } elseif ($eventName == 'create_quote') {
            return EVENT_CREATE_QUOTE;
        } elseif ($eventName == 'create_payment') {
            return EVENT_CREATE_PAYMENT;
        } else {
            return false;
        }
    }

    public static function notifyZapier($subscription, $data)
    {
        $curl = curl_init();

        $jsonEncodedData = json_encode($data->toPublicArray());
        
        $opts = [
            CURLOPT_URL => $subscription->target_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $jsonEncodedData,
            CURLOPT_HTTPHEADER  => ['Content-Type: application/json', 'Content-Length: '.strlen($jsonEncodedData)],
        ];

        curl_setopt_array($curl, $opts);

        $result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($status == 410) {
            $subscription->delete();
        }
    }


    public static function remapPublicIds($items)
    {
        $return = [];
        
        foreach ($items as $item) {
            $return[] = $item->toPublicArray();
        }

        return $return;
    }

    public static function hideIds($data)
    {
        $publicId = null;

        foreach ($data as $key => $val) {
            if (is_array($val)) {
                $data[$key] = Utils::hideIds($val);
            } else if ($key == 'id' || strpos($key, '_id')) {
                if ($key == 'public_id') {
                    $publicId = $val;
                }
                unset($data[$key]);
            }
        }

        if ($publicId) {
            $data['id'] = $publicId;
        }
        
        return $data;
    }

    public static function getApiHeaders($count = 0)
    {
        return [
          'Content-Type' => 'application/json',
          //'Access-Control-Allow-Origin' => '*',
          //'Access-Control-Allow-Methods' => 'GET',
          //'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization, X-Requested-With',
          //'Access-Control-Allow-Credentials' => 'true',
          'X-Total-Count' => $count,
          //'X-Rate-Limit-Limit' - The number of allowed requests in the current period
          //'X-Rate-Limit-Remaining' - The number of remaining requests in the current period
          //'X-Rate-Limit-Reset' - The number of seconds left in the current period,
        ];
    }

    public static function startsWith($haystack, $needle)
    {
        return $needle === "" || strpos($haystack, $needle) === 0;
    }

    public static function endsWith($haystack, $needle)
    {
        return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
    }

    public static function getEntityRowClass($model)
    {
        $str = $model->is_deleted || ($model->deleted_at && $model->deleted_at != '0000-00-00') ? 'DISABLED ' : '';

        if ($model->is_deleted) {
            $str .= 'ENTITY_DELETED ';
        }

        if ($model->deleted_at && $model->deleted_at != '0000-00-00') {
            $str .= 'ENTITY_ARCHIVED ';
        }

        return $str;
    }

    public static function exportData($output, $data)
    {
        if (count($data) > 0) {
            fputcsv($output, array_keys($data[0]));
        }

        foreach ($data as $record) {
            fputcsv($output, $record);
        }

        fwrite($output, "\n");
    }
    
    public static function stringToObjectResolution($baseObject, $rawPath)
    {
        $val = '';
        
        if (!is_object($baseObject)) {
          return $val;
        }
        
        $path = preg_split('/->/', $rawPath);
        $node = $baseObject;
        
        while (($prop = array_shift($path)) !== null) {
            if (property_exists($node, $prop)) {
                $val = $node->$prop;
                $node = $node->$prop;
            } else if (is_object($node) && isset($node->$prop)) {
                $node = $node->{$prop};
            } else if ( method_exists($node, $prop)) {
                $val = call_user_func(array($node, $prop));
            }
        }
        
        return $val;
    }

    public static function getFirst($values) {
        if (is_array($values)) {
            return count($values) ? $values[0] : false;
        } else {
            return $values;
        }
    }

    // nouns in German and French should be uppercase
    public static function transFlowText($key) {
        $str = trans("texts.$key");
        if (!in_array(App::getLocale(), ['de', 'fr'])) {
            $str = strtolower($str);
        }
        return $str;
    }

    public static function getSubdomainPlaceholder() {
        $parts = parse_url(SITE_URL);
        $subdomain = '';
        if (isset($parts['host'])) {
            $host = explode('.', $parts['host']);
            if (count($host) > 2) {
                $subdomain = $host[0];
            }
        }
        return $subdomain;
    }

    public static function getDomainPlaceholder() {
        $parts = parse_url(SITE_URL);
        $domain = '';
        if (isset($parts['host'])) {
            $host = explode('.', $parts['host']);
            if (count($host) > 2) {
                array_shift($host);
                $domain .= implode('.', $host);
            } else {
                $domain .= $parts['host'];
            }
        }
        if (isset($parts['path'])) {
            $domain .= $parts['path'];
        }
        return $domain;
    }

    public static function replaceSubdomain($domain, $subdomain) {
        $parsedUrl = parse_url($domain);
        $host = explode('.', $parsedUrl['host']);
        if (count($host) > 0) {
            $oldSubdomain = $host[0];
            $domain = str_replace("://{$oldSubdomain}.", "://{$subdomain}.", $domain);
        }
        return $domain;
    }
}
