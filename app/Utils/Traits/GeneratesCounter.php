<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Class GeneratesCounter
 * @package App\Utils\Traits
 */
trait GeneratesCounter
{


	/**
	 * Gets the next invoice number.
	 *
	 * @param      \App\Models\Client  $client  The client
	 *
	 * @return     string              The next invoice number.
	 */
	public function getNextInvoiceNumber(Client $client) :string
	{
		//Reset counters if enabled
		$this->resetCounters($client);

		$is_client_counter = false;

		//todo handle if we have specific client patterns in the future
		$pattern = $client->company->settings->invoice_number_pattern;

		//Determine if we are using client_counters
		if(strpos($pattern, 'client_counter') === false)
		{
			$counter = $client->company->settings->invoice_number_counter;
		}
		else 
		{
			$counter = $client->settings->invoice_number_counter;
			$is_client_counter = true;
		}

		//Return a valid counter
		$counter = $this->checkEntityNumber(Client::class, $client->company_id, $counter, $client->company->settings->counter_padding, $client->company->settings->invoice_number_prefix);

		//build number pattern and replace variables in pattern
		$invoice_number = $this->applyNumberPattern($client, $counter, $client->company->settings->invoice_number_pattern);
		
		//increment the correct invoice_number Counter (company vs client)
		if($is_client_counter)
			$this->incrementCounter($client, 'invoice_number_counter');
		else
			$this->incrementCounter($client->company, 'invoice_number_counter');

		return $invoice_number;
	}

	public function getNextCreditNumber(Client $client)
	{
		//Reset counters if enabled
		$this->resetCounters($client);

		//todo handle if we have specific client patterns in the future
		$pattern = $client->company->settings->credit_number_pattern;

		$counter = $this->checkEntityNumber(Credit::class, $client->company_id, $counter, $client->company->settings->counter_padding, $client->company->settings->invoice_number_prefix);

		$credit_number = $this->applyNumberPattern($client, $counter, $client->company->settings->credit_number_pattern);

	}

	public function getNextQuoteNumber()
	{

	}

	public function getNextRecurringInvoiceNumber()
	{

	}

	public function getNextClientInvoiceNumber()
	{

	}

	public function getNextClientNumber(Client $client)
	{
        //Reset counters if enabled
		$this->resetCounters($client);

        $counter = $client->company->getSettingsByKey( 'client_number_counter' );

		$client_number = $this->checkEntityNumber(Client::class, $counter, $client->company->settings->counter_padding, $client->company->settings->client_number_prefix, $client->company->settings->client_number_pattern);

		//$client_number = $this->applyNumberPattern($client, $counter, $client->company->settings->client_number_pattern);

		$this->incrementCounter($client->company, 'client_number_counter');

		return $client_number;
	}

	public function hasSharedCounter($client)
	{

		return $client->getSettingsByKey('shared_invoice_quote_counter') === TRUE;

	}

	/**
	 * Checks that the number has not already been used
	 *
	 * @param      Collection  $entity   The entity ie App\Models\Client, Invoice, Quote etc
	 * @param      integer  $counter  The counter
	 * @param      integer   $padding  The padding
	 * @param      string  $prefix   The prefix
	 *
	 * @return     string   The padded and prefixed invoice number
	 */
	private function checkEntityNumber($entity, $counter, $padding, $prefix, $pattern)
	{
		$check = false;

		do {

			$number = $this->padCounter($counter, $padding);

			if(isset($prefix))
				$number = $this->prefixCounter($number, $prefix);
			else
				$number = $this->applyNumberPattern($entity, $counter, $pattern);
			//todo
			$check = $entity::whereCompanyId($entity->company_id)->whereIdNumber($number)->withTrashed()->first();

			$counter++;

		} while ($check);

		
        return $number;
	}


	/**
	 * Saves counters at both the company and client level
	 *
	 * @param      \App\Models\Client                 $client        The client
	 * @param      \App\Models\Client|integer|string  $counter_name  The counter name
	 */
	private function incrementCounter($entity, string $counter_name) :void 
	{

		$settings = $entity->settings;
		$settings->$counter_name = $settings->$counter_name + 1;
		$entity->settings = $settings;
		$entity->save();

	}

	private function prefixCounter($counter, $prefix) : string
	{
		if(strlen($prefix) == 0)
			return $counter;

		return  $prefix . $counter;

	}

	/**
	 * Pads a number with leading 000000's
	 *
	 * @param      int  $counter  The counter
	 * @param      int  $padding  The padding
	 *
	 * @return     int  the padded counter
	 */
	private function padCounter($counter, $padding)
	{

		return str_pad($counter, $padding, '0', STR_PAD_LEFT);

	}


	/**
	 * If we are using counter reset, 
	 * check if we need to reset here
	 * 
	 * @param  Client $client client entity
	 * @return void
	 */
	private function resetCounters(Client $client)
    {

        $timezone = $client->company->timezone()->name;

Log::error('timezone = '.$timezone);

        $reset_date = Carbon::parse($client->company->settings->reset_counter_date, $timezone);

Log::error('reset date = '. $reset_date->format('Y-m-d'));

        if (! $reset_date->isToday() || ! $client->company->settings->reset_counter_date) 
            return false;

Log::error('we are resetting here!!');

        switch ($client->company->reset_counter_frequency_id) {
            case RecurringInvoice::FREQUENCY_WEEKLY:
                $reset_date->addWeek();
                break;
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                $reset_date->addWeeks(2);
                break;
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                $reset_date->addWeeks(4);
                break;
            case RecurringInvoice::FREQUENCY_MONTHLY:
                $reset_date->addMonth();
                break;
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                $reset_date->addMonths(2);
                break;
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                $reset_date->addMonths(3);
                break;
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                $reset_date->addMonths(4);
                break;
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                $reset_date->addMonths(6);
                break;
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                $reset_date->addYear();
                break;
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                $reset_date->addYears(2);
                break;
        }

        $settings = $client->company->settings;
        $settings->reset_counter_date = $reset_date->format('Y-m-d');
        $settings->invoice_number_counter = 1;
        $settings->quote_number_counter = 1;
        $settings->credit_number_counter = 1;

        $client->company->settings = $settings;
        $client->company->save();
    }

    private function applyNumberPattern($entity, $counter, $pattern)
    {
    	if(!$pattern)
			return $counter;

		if($entity instanceof Client)
			$client = $entity;
		else
			$client = $entity->client;

        $search = ['{$year}'];
        $replace = [date('Y')];

        $search[] = '{$counter}';
        $replace[] = $counter;

        $search[] = '{$client_counter}';
        $replace[] = $counter;

        if (strstr($pattern, '{$user_id}')) {
            $user_id = $entity->user_id ? $entity->user_ids : 0;
            $search[] = '{$user_id}';
            $replace[] = str_pad(($user_id), 2, '0', STR_PAD_LEFT);
        }

        $matches = false;
        preg_match('/{\$date:(.*?)}/', $pattern, $matches);
        if (count($matches) > 1) {
            $format = $matches[1];
            $search[] = $matches[0];

            /* The following adjusts for the company timezone - may bork tests depending on the time of day the tests are run!!!!!!*/
            $date = Carbon::now($entity->company->timezone()->name)->format($format);
            $replace[] = str_replace($format, $date, $matches[1]);
        }

        $search[] = '{$custom1}';
        $replace[] = $client->custom_value1;

        $search[] = '{$custom2}';
        $replace[] = $client->custom_value1;

        $search[] = '{$custom3}';
        $replace[] = $client->custom_value1;

        $search[] = '{$custom4}';
        $replace[] = $client->custom_value1;

        $search[] = '{$id_number}';
        $replace[] = $client->id_number;
//Log::error($search);
//Log::error($replace);
//Log::error($pattern);
        return str_replace($search, $replace, $pattern);

    }

}