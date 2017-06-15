<?php

namespace App\Libraries;

use App;
use Auth;
use Cache;
use Carbon;
use DateTime;
use DateTimeZone;
use Exception;
use Input;
use Log;
use Request;
use Schema;
use Session;
use stdClass;
use View;
use WePay;

class Utils
{
    private static $weekdayNames = [
        'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday',
    ];

    public static $months = [
        'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december',
    ];

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

    public static function isSelfHost()
    {
        return ! static::isNinjaProd();
    }

    public static function isNinjaProd()
    {
        if (self::isReseller()) {
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

        return self::isNinjaProd() || (isset($_ENV['REQUIRE_HTTPS']) && $_ENV['REQUIRE_HTTPS'] == 'true');
    }

    public static function isReseller()
    {
        return self::getResllerType() ? true : false;
    }

	public static function clientViewCSS()
	{
		$account = false;

		if (Auth::check()) {
			$account = Auth::user()->account;
		} elseif ($contactKey = session('contact_key')) {
			if ($contact = \App\Models\Contact::whereContactKey($contactKey)->first()) {
				$account = $contact->account;
			}
		}

		if ( !$account && ! self::isNinja()) {
			// For self-hosted accounts, pick the first account
			$account = \App\Models\Account::first();
		}

		return $account ? $account->clientViewCSS() : '';
	}

	public static function getAccountFontsUrl($protocol = '')
	{
		$account = false;

		if (Auth::check()) {
			$account = Auth::user()->account;
		} elseif ($contactKey = session('contact_key')) {
			if ($contact = \App\Models\Contact::whereContactKey($contactKey)->first()) {
				$account = $contact->account;
			}
		}

		if ( !$account && ! self::isNinja()) {
			// For self-hosted accounts, pick the first account
			$account = \App\Models\Account::first();
		}

		return $account ? $account->getFontsUrl($protocol) : false;
	}

    public static function isWhiteLabel()
    {
        $account = false;

        if (self::isNinja()) {
            if (Auth::check()) {
                $account = Auth::user()->account;
            } elseif ($contactKey = session('contact_key')) {
                if ($contact = \App\Models\Contact::whereContactKey($contactKey)->first()) {
                    $account = $contact->account;
                }
            }
        } else {
            $account = \App\Models\Account::first();
        }

        return $account ? $account->hasFeature(FEATURE_WHITE_LABEL) : false;
    }

    public static function getResllerType()
    {
        return isset($_ENV['RESELLER_TYPE']) ? $_ENV['RESELLER_TYPE'] : false;
    }

    public static function getTermsLink()
    {
        return static::isNinja() ? NINJA_WEB_URL.'/terms' : NINJA_WEB_URL.'/self-hosting-the-invoice-ninja-platform';
    }

    public static function isOAuthEnabled()
    {
        $providers = [
            SOCIAL_GOOGLE,
            SOCIAL_FACEBOOK,
            SOCIAL_GITHUB,
            SOCIAL_LINKEDIN,
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
        return self::isNinja() || Auth::check();
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

    public static function isPaidPro()
    {
        return static::isPro() && ! static::isTrial();
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
        if (self::isNinja()) {
            return USER_TYPE_CLOUD_HOST;
        } else {
            return USER_TYPE_SELF_HOST;
        }
    }

    public static function getNewsFeedResponse($userType = false)
    {
        if (! $userType) {
            $userType = self::getUserType();
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
                && ! Auth::user()->isPro()
                && $feature == ACCOUNT_ADVANCED_SETTINGS) {
            return '&nbsp;<sup class="pro-label">PRO</sup>';
        } else {
            return '';
        }
    }

    public static function getPlanPrice($plan)
    {
        $term = $plan['term'];
        $numUsers = $plan['num_users'];
        $plan = $plan['plan'];

        if ($plan == PLAN_FREE) {
            $price = 0;
        } elseif ($plan == PLAN_PRO) {
            $price = PLAN_PRICE_PRO_MONTHLY;
        } elseif ($plan == PLAN_ENTERPRISE) {
            if ($numUsers <= 2) {
                $price = PLAN_PRICE_ENTERPRISE_MONTHLY_2;
            } elseif ($numUsers <= 5) {
                $price = PLAN_PRICE_ENTERPRISE_MONTHLY_5;
            } elseif ($numUsers <= 10) {
                $price = PLAN_PRICE_ENTERPRISE_MONTHLY_10;
            } elseif ($numUsers <= 20) {
                $price = PLAN_PRICE_ENTERPRISE_MONTHLY_20;
            } else {
                static::fatalError('Invalid number of users: ' . $numUsers);
            }
        }

        if ($term == PLAN_TERM_YEARLY) {
            $price = $price * 10;
        }

        return $price;
    }

    public static function getMinNumUsers($max)
    {
        if ($max <= 2) {
            return 1;
        } elseif ($max <= 5) {
            return 3;
        } elseif ($max <= 10) {
            return 6;
        } else {
            return 11;
        }
    }

    public static function basePath()
    {
        return substr($_SERVER['SCRIPT_NAME'], 0, strrpos($_SERVER['SCRIPT_NAME'], '/') + 1);
    }

    public static function trans($input, $module = false)
    {
        $data = [];

        foreach ($input as $field) {
            if ($field == 'checkbox') {
                $data[] = $field;
            } elseif ($field) {
                if ($module) {
                    $data[] = mtrans($module, $field);
                } else {
                    $data[] = trans("texts.$field");
                }
            } else {
                $data[] = '';
            }
        }

        return $data;
    }

    public static function fatalError($message = false, $exception = false)
    {
        if (! $message) {
            $message = 'An error occurred, please try again later.';
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

        $data = static::prepareErrorData($context);

        if ($info) {
            Log::info($error."\n", $data);
        } else {
            Log::error($error."\n", $data);
        }
    }

    public static function prepareErrorData($context)
    {
        return [
            'context' => $context,
            'user_id' => Auth::check() ? Auth::user()->id : 0,
            'account_id' => Auth::check() ? Auth::user()->account_id : 0,
            'user_name' => Auth::check() ? Auth::user()->getDisplayName() : '',
            'method' => Request::method(),
            'url' => Input::get('url', Request::url()),
            'previous' => url()->previous(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'ip' => Request::getClientIp(),
            'count' => Session::get('error_count', 0),
            'is_console' => App::runningInConsole() ? 'yes' : 'no',
            'is_api' => session('token_id') ? 'yes' : 'no',
            'db_server' => config('database.default'),
        ];
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

    public static function getFromCache($id, $type)
    {
        $cache = Cache::get($type);

        if (! $cache) {
            static::logError("Cache for {$type} is not set");

            return null;
        }

        $data = $cache->filter(function ($item) use ($id) {
            return $item->id == $id;
        });

        return $data->first();
    }

    public static function formatMoney($value, $currencyId = false, $countryId = false, $decorator = false)
    {
        $value = floatval($value);

        if (! $currencyId) {
            $currencyId = Session::get(SESSION_CURRENCY, DEFAULT_CURRENCY);
        }

        if (! $decorator) {
            $decorator = Session::get(SESSION_CURRENCY_DECORATOR, CURRENCY_DECORATOR_SYMBOL);
        }

        if (! $countryId && Auth::check()) {
            $countryId = Auth::user()->account->country_id;
        }

        $currency = self::getFromCache($currencyId, 'currencies');
        $thousand = $currency->thousand_separator;
        $decimal = $currency->decimal_separator;
        $precision = $currency->precision;
        $code = $currency->code;
        $swapSymbol = $currency->swap_currency_symbol;

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

        if ($decorator == CURRENCY_DECORATOR_NONE) {
            return $value;
        } elseif ($decorator == CURRENCY_DECORATOR_CODE || ! $symbol) {
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

    public static function pluralizeEntityType($type)
    {
        if (! self::isNinjaProd()) {
            if ($module = \Module::find($type)) {
                return $module->get('plural', $type);
            }
        }

        if ($type === ENTITY_EXPENSE_CATEGORY) {
            return 'expense_categories';
        } else {
            return $type . 's';
        }
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
        return lcfirst(static::toClassCase($string));
    }

    public static function toClassCase($string)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

    public static function timestampToDateTimeString($timestamp)
    {
        $timezone = Session::get(SESSION_TIMEZONE, DEFAULT_TIMEZONE);
        $format = Session::get(SESSION_DATETIME_FORMAT, DEFAULT_DATETIME_FORMAT);

        return self::timestampToString($timestamp, $timezone, $format);
    }

    public static function timestampToDateString($timestamp)
    {
        $timezone = Session::get(SESSION_TIMEZONE, DEFAULT_TIMEZONE);
        $format = Session::get(SESSION_DATE_FORMAT, DEFAULT_DATE_FORMAT);

        return self::timestampToString($timestamp, $timezone, $format);
    }

    public static function dateToString($date)
    {
        if (! $date) {
            return false;
        }

        if ($date instanceof DateTime) {
            $dateTime = $date;
        } else {
            $dateTime = new DateTime($date);
        }

        $timestamp = $dateTime->getTimestamp();
        $format = Session::get(SESSION_DATE_FORMAT, DEFAULT_DATE_FORMAT);

        return self::timestampToString($timestamp, false, $format);
    }

    public static function timestampToString($timestamp, $timezone, $format)
    {
        if (! $timestamp) {
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
        if (! $date) {
            return;
        }

        $format = Session::get(SESSION_DATE_FORMAT, DEFAULT_DATE_FORMAT);
        $dateTime = DateTime::createFromFormat($format, $date);

        if (! $dateTime) {
            return $date;
        } else {
            return $formatResult ? $dateTime->format('Y-m-d') : $dateTime;
        }
    }

    public static function fromSqlDate($date, $formatResult = true)
    {
        if (! $date || $date == '0000-00-00') {
            return '';
        }

        $format = Session::get(SESSION_DATE_FORMAT, DEFAULT_DATE_FORMAT);
        $dateTime = DateTime::createFromFormat('Y-m-d', $date);

        if (! $dateTime) {
            return $date;
        } else {
            return $formatResult ? $dateTime->format($format) : $dateTime;
        }
    }

    public static function fromSqlDateTime($date, $formatResult = true)
    {
        if (! $date || $date == '0000-00-00 00:00:00') {
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

        return sprintf('%02d%s%02d%s%02d', floor($t / 3600), $f, ($t / 60) % 60, $f, $t % 60);
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

    public static function processVariables($str)
    {
        if (! $str) {
            return '';
        }

        $variables = ['MONTH', 'QUARTER', 'YEAR'];
        for ($i = 0; $i < count($variables); $i++) {
            $variable = $variables[$i];
            $regExp = '/:'.$variable.'[+-]?[\d]*/';
            preg_match_all($regExp, $str, $matches);
            $matches = $matches[0];
            if (count($matches) == 0) {
                continue;
            }
            usort($matches, function ($a, $b) {
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

                $val = self::getDatePart($variable, $offset);
                $str = str_replace($match, $val, $str);
            }
        }

        return $str;
    }

    private static function getDatePart($part, $offset)
    {
        $offset = intval($offset);
        if ($part == 'MONTH') {
            return self::getMonth($offset);
        } elseif ($part == 'QUARTER') {
            return self::getQuarter($offset);
        } elseif ($part == 'YEAR') {
            return self::getYear($offset);
        }
    }

    public static function getMonthOptions()
    {
        $months = [];

        for ($i = 1; $i <= count(static::$months); $i++) {
            $month = static::$months[$i - 1];
            $number = $i < 10 ? '0' . $i : $i;
            $months["2000-{$number}-01"] = trans("texts.{$month}");
        }

        return $months;
    }

    private static function getMonth($offset)
    {
        $months = static::$months;
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

    public static function getEntityName($entityType)
    {
        return ucwords(self::toCamelCase($entityType));
    }

    public static function getClientDisplayName($model)
    {
        if ($model->client_name) {
            return $model->client_name;
        } elseif ($model->first_name || $model->last_name) {
            return $model->first_name.' '.$model->last_name;
        } else {
            return $model->email ?: '';
        }
    }

    public static function getVendorDisplayName($model)
    {
        if (is_null($model)) {
            return '';
        }

        if ($model->vendor_name) {
            return $model->vendor_name;
        }

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
        for ($i = 0; $i < 5; $i++) {
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
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: '.strlen($jsonEncodedData)],
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
        return ! $value || $value == '0' || $value == '0.00' || $value == '0,00';
    }

    public static function startsWith($haystack, $needle)
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }

    public static function endsWith($haystack, $needle)
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
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
    // TODO remove this
    public static function transFlowText($key)
    {
        $str = trans("texts.$key");
        if (! in_array(App::getLocale(), ['de', 'fr'])) {
            $str = strtolower($str);
        }

        return $str;
    }

    public static function getSubdomain($url)
    {
        $parts = parse_url($url);
        $subdomain = '';

        if (isset($parts['host'])) {
            $host = explode('.', $parts['host']);
            if (count($host) > 2) {
                $subdomain = $host[0];
            }
        }

        return $subdomain;
    }

    public static function getSubdomainPlaceholder()
    {
        return static::getSubdomain(SITE_URL);
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

        return [$firstName, $lastName];
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
        if (! $website) {
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

        return link_to($link, $title, ['target' => '_blank']);
    }

    public static function wrapAdjustment($adjustment, $currencyId, $countryId)
    {
        $class = $adjustment <= 0 ? 'success' : 'default';
        $adjustment = self::formatMoney($adjustment, $currencyId, $countryId);

        return "<h4><div class=\"label label-{$class}\">$adjustment</div></h4>";
    }

    public static function copyContext($entity1, $entity2)
    {
        if (! $entity2) {
            return $entity1;
        }

        $fields = [
            'contact_id',
            'payment_id',
            'invoice_id',
            'credit_id',
            'invitation_id',
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
        if (! preg_match('~^(?:f|ht)tps?://~i', $url)) {
            $url = 'http://' . $url;
        }

        return $url;
    }

    public static function setupWePay($accountGateway = null)
    {
        if (WePay::getEnvironment() == 'none') {
            if (WEPAY_ENVIRONMENT == WEPAY_STAGE) {
                WePay::useStaging(WEPAY_CLIENT_ID, WEPAY_CLIENT_SECRET);
            } else {
                WePay::useProduction(WEPAY_CLIENT_ID, WEPAY_CLIENT_SECRET);
            }
        }

        if ($accountGateway) {
            return new WePay($accountGateway->getConfig()->accessToken);
        } else {
            return new WePay(null);
        }
    }

    /**
     * Gets an array of weekday names (in English).
     *
     * @see getTranslatedWeekdayNames()
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getWeekdayNames()
    {
        return collect(static::$weekdayNames);
    }

    /**
     * Gets an array of translated weekday names.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getTranslatedWeekdayNames()
    {
        return collect(static::$weekdayNames)->transform(function ($day) {
            return trans('texts.'.strtolower($day));
        });
    }

    public static function getReadableUrl($path)
    {
        $url = static::getDocsUrl($path);

        $parts = explode('/', $url);
        $part = $parts[count($parts) - 1];
        $part = str_replace('#', '> ', $part);
        $part = str_replace(['.html', '-', '_'], ' ', $part);

        if ($part) {
            return trans('texts.user_guide') . ': ' . ucwords($part);
        } else {
            return trans('texts.user_guide');
        }
    }

    public static function getDocsUrl($path)
    {
        $page = '';
        $parts = explode('/', $path);
        $first = count($parts) ? $parts[0] : false;
        $second = count($parts) > 1 ? $parts[1] : false;

        $entityTypes = [
            'clients',
            'invoices',
            'payments',
            'recurring_invoices',
            'credits',
            'quotes',
            'tasks',
            'expenses',
            'vendors',
        ];

        if ($path == 'dashboard') {
            $page = '/introduction.html#dashboard';
        } elseif (in_array($path, $entityTypes)) {
            $page = "/{$path}.html#list-" . str_replace('_', '-', $path);
        } elseif (in_array($first, $entityTypes)) {
            $action = ($first == 'payments' || $first == 'credits') ? 'enter' : 'create';
            $page = "/{$first}.html#{$action}-" . substr(str_replace('_', '-', $first), 0, -1);
        } elseif ($first == 'expense_categories') {
            $page = '/expenses.html#expense-categories';
        } elseif ($first == 'settings') {
            if ($second == 'bank_accounts') {
                $page = ''; // TODO write docs
            } elseif (in_array($second, \App\Models\Account::$basicSettings)) {
                if ($second == 'products') {
                    $second = 'product_library';
                } elseif ($second == 'notifications') {
                    $second = 'email_notifications';
                }
                $page = '/settings.html#' . str_replace('_', '-', $second);
            } elseif (in_array($second, \App\Models\Account::$advancedSettings)) {
                $page = "/{$second}.html";
            } elseif ($second == 'customize_design') {
                $page = '/invoice_design.html#customize';
            }
        } elseif ($first == 'tax_rates') {
            $page = '/settings.html#tax-rates';
        } elseif ($first == 'products') {
            $page = '/settings.html#product-library';
        } elseif ($first == 'users') {
            $page = '/user_management.html#create-user';
        }

        return url(NINJA_DOCS_URL . $page);
    }

    public static function calculateTaxes($amount, $taxRate1, $taxRate2)
    {
        $tax1 = round($amount * $taxRate1 / 100, 2);
        $tax2 = round($amount * $taxRate2 / 100, 2);

        return round($amount + $tax1 + $tax2, 2);
    }

    public static function truncateString($string, $length)
    {
        return strlen($string) > $length ? rtrim(substr($string, 0, $length)) . '...' : $string;
    }

    // http://stackoverflow.com/a/14238078/497368
    public static function isInterlaced($filename)
    {
       $handle = fopen($filename, 'r');
       $contents = fread($handle, 32);
       fclose($handle);
       return( ord($contents[28]) != 0 );
    }
}
