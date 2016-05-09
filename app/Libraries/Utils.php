<?php namespace App\Libraries;

use Auth;
use Cache;
use DB;
use App;
use Schema;
use Session;
use Request;
use Exception;
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
        } catch (Exception $e) {
            return false;
        }
    }

    public static function isDownForMaintenance()
    {
        return file_exists(storage_path() . '/framework/down');
    }

    public static function isCron()
    {
        return php_sapi_name() == 'cli';
    }

    public static function isTravis()
    {
        return env('TRAVIS') == 'true';
    }

    public static function isNinja()
    {
        return self::isNinjaProd() || self::isNinjaDev();
    }

    public static function isNinjaProd()
    {
        if (Utils::isReseller()) {
            return true;
        }

        return env('NINJA_PROD') == 'true';
    }

    public static function isNinjaDev()
    {
        return env('NINJA_DEV') == 'true';
    }

    public static function requireHTTPS()
    {
        if (Request::root() === 'http://ninja.dev' || Request::root() === 'http://ninja.dev:8000') {
            return false;
        }

        return Utils::isNinjaProd() || (isset($_ENV['REQUIRE_HTTPS']) && $_ENV['REQUIRE_HTTPS'] == 'true');
    }

    public static function isReseller()
    {
        return Utils::getResllerType() ? true : false;
    }

    public static function getResllerType()
    {
        return isset($_ENV['RESELLER_TYPE']) ? $_ENV['RESELLER_TYPE'] : false;
    }

    public static function isOAuthEnabled()
    {
        $providers = [
            SOCIAL_GOOGLE,
            SOCIAL_FACEBOOK,
            SOCIAL_GITHUB,
            SOCIAL_LINKEDIN
        ];

        foreach ($providers as $provider) {
            $key = strtoupper($provider) . '_CLIENT_ID';
            if (isset($_ENV[$key]) && $_ENV[$key]) {
                return true;
            }
        }

        return false;
    }

    public static function allowNewAccounts()
    {
        return Utils::isNinja() || Auth::check();
    }

    public static function isPro()
    {
        return Auth::check() && Auth::user()->isPro();
    }

    public static function hasFeature($feature)
    {
        return Auth::check() && Auth::user()->hasFeature($feature);
    }

    public static function isAdmin()
    {
        return Auth::check() && Auth::user()->is_admin;
    }

    public static function hasPermission($permission, $requireAll = false)
    {
        return Auth::check() && Auth::user()->hasPermission($permission, $requireAll);
    }

    public static function hasAllPermissions($permission)
    {
        return Auth::check() && Auth::user()->hasPermission($permission);
    }

    public static function isTrial()
    {
        return Auth::check() && Auth::user()->isTrial();
    }

    public static function isEnglish()
    {
        return App::getLocale() == 'en';
    }
    
    public static function getLocaleRegion()
    {
        $parts = explode('_', App::getLocale()); 
        
        return count($parts) ? $parts[0] : 'en';
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

    public static function getLastURL()
    {
        if (!count(Session::get(RECENTLY_VIEWED))) {
            return '#';
        }

        $history = Session::get(RECENTLY_VIEWED);
        $last = $history[0];
        $penultimate = count($history) > 1 ? $history[1] : $last;

        return Request::url() == $last->url ? $penultimate->url : $last->url;
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
            } elseif ($field) {
                $data[] = trans("texts.$field");
            } else {
                $data[] = '';
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

    public static function logError($error, $context = 'PHP', $info = false)
    {
        if ($error instanceof Exception) {
            $error = self::getErrorString($error);
        }

        $count = Session::get('error_count', 0);
        Session::put('error_count', ++$count);
        if ($count > 200) {
            return 'logged';
        }

        $data = [
            'context' => $context,
            'user_id' => Auth::check() ? Auth::user()->id : 0,
            'account_id' => Auth::check() ? Auth::user()->account_id : 0,
            'user_name' => Auth::check() ? Auth::user()->getDisplayName() : '',
            'method' => Request::method(),
            'url' => Input::get('url', Request::url()),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'ip' => Request::getClientIp(),
            'count' => Session::get('error_count', 0),
        ];

        if ($info) {
            Log::info($error."\n", $data);
        } else {
            Log::error($error."\n", $data);    
        }

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

    public static function parseInt($value)
    {
        $value = preg_replace('/[^0-9]/', '', $value);

        return intval($value);
    }

    public static function getFromCache($id, $type) {
        $cache = Cache::get($type);
        
        if ( ! $cache) {
            static::logError("Cache for {$type} is not set");
            return null;
        }
        
        $data = $cache->filter(function($item) use ($id) {
            return $item->id == $id;
        });

        return $data->first();
    }

    public static function formatMoney($value, $currencyId = false, $countryId = false, $showCode = false)
    {
        if (!$value) {
            $value = 0;
        }

        if (!$currencyId) {
            $currencyId = Session::get(SESSION_CURRENCY, DEFAULT_CURRENCY);
        }

        if (!$countryId && Auth::check()) {
            $countryId = Auth::user()->account->country_id;
        }

        $currency = self::getFromCache($currencyId, 'currencies');
        $thousand = $currency->thousand_separator;
        $decimal = $currency->decimal_separator;
        $precision = $currency->precision;
        $code = $currency->code;
        $swapSymbol = false;

        if ($countryId && $currencyId == CURRENCY_EURO) {
            $country = self::getFromCache($countryId, 'countries');
            $swapSymbol = $country->swap_currency_symbol;
            if ($country->thousand_separator) {
                $thousand = $country->thousand_separator;
            }
            if ($country->decimal_separator) {
                $decimal = $country->decimal_separator;
            }
        }

        $value = number_format($value, $precision, $decimal, $thousand);
        $symbol = $currency->symbol;

        if ($showCode || !$symbol) {
            return "{$value} {$code}";
        } elseif ($swapSymbol) {
            return "{$value} " . trim($symbol);
        } else {
            return "{$symbol}{$value}";
        }
    }

    public static function pluralize($string, $count)
    {
        $field = $count == 1 ? $string : $string.'s';
        $string = trans("texts.$field", ['count' => $count]);

        return $string;
    }

    public static function maskAccountNumber($value)
    {
        $length = strlen($value);
        if ($length < 4) {
            str_repeat('*', 16);
        }

        $lastDigits = substr($value, -4);
        return str_repeat('*', $length - 4) . $lastDigits;
    }

    // http://wephp.co/detect-credit-card-type-php/
    public static function getCardType($number)
    {
        $number = preg_replace('/[^\d]/', '', $number);

        if (preg_match('/^3[47][0-9]{13}$/', $number)) {
            return 'American Express';
        } elseif (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/', $number)) {
            return 'Diners Club';
        } elseif (preg_match('/^6(?:011|5[0-9][0-9])[0-9]{12}$/', $number)) {
            return 'Discover';
        } elseif (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/', $number)) {
            return 'JCB';
        } elseif (preg_match('/^5[1-5][0-9]{14}$/', $number)) {
            return 'MasterCard';
        } elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) {
            return 'Visa';
        } else {
            return 'Unknown';
        }
    }

    public static function toArray($data)
    {
        return json_decode(json_encode((array) $data), true);
    }

    public static function toSpaceCase($string)
    {
        return preg_replace('/([a-z])([A-Z])/s', '$1 $2', $string);
    }

    public static function toSnakeCase($string)
    {
        return preg_replace('/([a-z])([A-Z])/s', '$1_$2', $string);
    }

    public static function toCamelCase($string)
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $string))));
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
        if (!$date) {
            return false;
        }

        if ($date instanceof DateTime) {
            $dateTime = $date;
        } else {
            $dateTime = new DateTime($date);
        }

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

    public static function toSqlDate($date, $formatResult = true)
    {
        if (!$date) {
            return;
        }

        $format = Session::get(SESSION_DATE_FORMAT, DEFAULT_DATE_FORMAT);
        $dateTime = DateTime::createFromFormat($format, $date);

        if(!$dateTime)
            return $date;
        else
            return $formatResult ? $dateTime->format('Y-m-d') : $dateTime;
    }

    public static function fromSqlDate($date, $formatResult = true)
    {
        if (!$date || $date == '0000-00-00') {
            return '';
        }

        $format = Session::get(SESSION_DATE_FORMAT, DEFAULT_DATE_FORMAT);
        $dateTime = DateTime::createFromFormat('Y-m-d', $date);

        if(!$dateTime)
            return $date;
        else
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

    public static function formatTime($t)
    {
        // http://stackoverflow.com/a/3172665
        $f = ':';
        return sprintf("%02d%s%02d%s%02d", floor($t/3600), $f, ($t/60)%60, $f, $t%60);
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

            array_push($data, $item);

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
        $months = [ "january", "february", "march", "april", "may", "june",
            "july", "august", "september", "october", "november", "december", ];

        $month = intval(date('n')) - 1;

        $month += $offset;
        $month = $month % 12;

        if ($month < 0) {
            $month += 12;
        }

        return trans('texts.' . $months[$month]);
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

    public static function getEntityClass($entityType)
    {
        return 'App\\Models\\' . static::getEntityName($entityType);
    }

    public static function getEntityName($entityType)
    {
        return ucwords(Utils::toCamelCase($entityType));
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

    public static function getVendorDisplayName($model)
    {
        if(is_null($model))
            return '';

        if($model->vendor_name)
            return $model->vendor_name;

        return 'No vendor name';
    }

    public static function getPersonDisplayName($firstName, $lastName, $email)
    {
        if ($firstName || $lastName) {
            return $firstName.' '.$lastName;
        } elseif ($email) {
            return $email;
        } else {
            return trans('texts.guest');
        }
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
        } elseif ($eventName == 'create_vendor') {
            return EVENT_CREATE_VENDOR;
        } else {
            return false;
        }
    }

    public static function notifyZapier($subscription, $data)
    {
        $curl = curl_init();
        $jsonEncodedData = json_encode($data);

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

    public static function getApiHeaders($count = 0)
    {
        return [
          'Content-Type' => 'application/json',
          //'Access-Control-Allow-Origin' => '*',
          //'Access-Control-Allow-Methods' => 'GET',
          //'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization, X-Requested-With',
          //'Access-Control-Allow-Credentials' => 'true',
          'X-Total-Count' => $count,
          'X-Ninja-Version' => NINJA_VERSION,
          //'X-Rate-Limit-Limit' - The number of allowed requests in the current period
          //'X-Rate-Limit-Remaining' - The number of remaining requests in the current period
          //'X-Rate-Limit-Reset' - The number of seconds left in the current period,
        ];
    }

    public static function isEmpty($value)
    {
        return !$value || $value == '0' || $value == '0.00' || $value == '0,00';
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
        $str = '';

        if (property_exists($model, 'is_deleted')) {
            $str = $model->is_deleted || ($model->deleted_at && $model->deleted_at != '0000-00-00') ? 'DISABLED ' : '';

            if ($model->is_deleted) {
                $str .= 'ENTITY_DELETED ';
            }
        }

        if ($model->deleted_at && $model->deleted_at != '0000-00-00') {
            $str .= 'ENTITY_ARCHIVED ';
        }

        return $str;
    }

    public static function exportData($output, $data, $headers = false)
    {
        if ($headers) {
            fputcsv($output, $headers);
        } elseif (count($data) > 0) {
            fputcsv($output, array_keys($data[0]));
        }

        foreach ($data as $record) {
            fputcsv($output, $record);
        }

        fwrite($output, "\n");
    }

    public static function getFirst($values)
    {
        if (is_array($values)) {
            return count($values) ? $values[0] : false;
        } else {
            return $values;
        }
    }

    // nouns in German and French should be uppercase
    public static function transFlowText($key)
    {
        $str = trans("texts.$key");
        if (!in_array(App::getLocale(), ['de', 'fr'])) {
            $str = strtolower($str);
        }
        return $str;
    }

    public static function getSubdomainPlaceholder()
    {
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

    public static function getDomainPlaceholder()
    {
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

    public static function replaceSubdomain($domain, $subdomain)
    {
        $parsedUrl = parse_url($domain);
        $host = explode('.', $parsedUrl['host']);
        if (count($host) > 0) {
            $oldSubdomain = $host[0];
            $domain = str_replace("://{$oldSubdomain}.", "://{$subdomain}.", $domain);
        }
        return $domain;
    }

    public static function splitName($name)
    {
        $name = trim($name);
        $lastName = (strpos($name, ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $name);
        $firstName = trim(preg_replace('#'.$lastName.'#', '', $name));
        return array($firstName, $lastName);
    }

    public static function decodePDF($string)
    {
        $string = str_replace('data:application/pdf;base64,', '', $string);
        return base64_decode($string);
    }

    public static function cityStateZip($city, $state, $postalCode, $swap)
    {
        $str = $city;

        if ($state) {
            if ($str) {
                $str .= ', ';
            }
            $str .= $state;
        }

        if ($swap) {
            return $postalCode . ' ' . $str;
        } else {
            return $str . ' ' . $postalCode;
        }
    }

    public static function formatWebsite($website)
    {
        if (!$website) {
            return '';
        }

        $link = $website;
        $title = $website;
        $prefix = 'http://';

        if (strlen($link) > 7 && substr($link, 0, 7) === $prefix) {
            $title = substr($title, 7);
        } else {
            $link = $prefix.$link;
        }

        return link_to($link, $title, array('target' => '_blank'));
    }

    public static function wrapAdjustment($adjustment, $currencyId, $countryId)
    {
        $class = $adjustment <= 0 ? 'success' : 'default';
        $adjustment = Utils::formatMoney($adjustment, $currencyId, $countryId);
        return "<h4><div class=\"label label-{$class}\">$adjustment</div></h4>";
    }

    public static function copyContext($entity1, $entity2)
    {
        if (!$entity2) {
            return $entity1;
        }

        $fields = [
            'contact_id',
            'payment_id',
            'invoice_id',
            'credit_id',
            'invitation_id'
        ];

        $fields1 = $entity1->getAttributes();
        $fields2 = $entity2->getAttributes();

        foreach ($fields as $field) {
            if (isset($fields2[$field]) && $fields2[$field]) {
                $entity1->$field = $entity2->$field;
            }
        }

        return $entity1;
    }

    public static function addHttp($url)
    {
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = "http://" . $url;
        }

        return $url;
    }
}
