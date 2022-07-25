<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits;

use Carbon\Carbon;
use DateTime;
use DateTimeZone;

/**
 * Class MakesDates.
 */
trait MakesDates
{
    /**
     * Converts from UTC to client timezone.
     * @param  datetime 	object 		$utc_date
     * @param  string 		$timezone 	ie Australia/Sydney
     * @return Carbon           		Carbon object
     */
    public function createClientDate($utc_date, $timezone)
    {
        if (is_string($utc_date)) {
            $utc_date = $this->convertToDateObject($utc_date);
        }

        return $utc_date->setTimezone(new DateTimeZone($timezone));
    }

    /**
     * Converts from client timezone to UTC.
     * @param datetime    object        $utc_date
     * @return Carbon                Carbon object
     */
    public function createUtcDate($client_date)
    {
        if (is_string($client_date)) {
            $client_date = $this->convertToDateObject($client_date);
        }

        return $client_date->setTimezone(new DateTimeZone('GMT'));
    }

    /**
     * Formats a date.
     * @param  Carbon|string $date   Carbon object or date string
     * @param  string $format The date display format
     * @return string         The formatted date
     */
    public function formatDate($date, string $format) :string
    {
        if (! isset($date)) {
            return '';
        }

        if (is_string($date)) {
            $date = $this->convertToDateObject($date);
        }

        return $date->format($format);
    }

    /**
     * Formats a datedate.
     * @param  $date   Carbon object or date string
     * @param  string $format The date display format
     * @return string         The formatted date
     */
    public function formatDatetime($date, string $format) :string
    {
        return Carbon::createFromTimestamp($date)->format($format.' g:i a');
    }

    /**
     * Formats a date.
     * @param  Carbon/String $date   Carbon object or date string
     * @param  string $format The date display format
     * @return string         The formatted date
     */
    public function formatDateTimestamp($timestamp, string $format) :string
    {
        return Carbon::createFromTimestamp($timestamp)->format($format);
    }

    private function convertToDateObject($date)
    {
        $dt = new DateTime($date);
        $dt->setTimezone(new DateTimeZone('UTC'));

        return $dt;
    }

    public function translateDate($date, $format, $locale)
    {
        if (empty($date)) {
            return '';
        }

        Carbon::setLocale($locale);

        try {
            return Carbon::parse($date)->translatedFormat($format);
        } catch (\Exception $e) {
            return 'Invalid date!';
        }
    }
}
