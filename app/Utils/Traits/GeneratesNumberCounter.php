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
 * Class GeneratesNumberCounter
 * @package App\Utils\Traits
 */
trait GeneratesNumberCounter
{

	public function getNextNumber($entity)
	{
        $entity_name = $this->entityName($entity);

		$counter = $this->getCounter($entity);
		$counter_offset = 0;
        $prefix = $this->getNumberPrefix($entity);
        $lastNumber = false;


		$check = false;

		do {
            if ($this->hasNumberPattern($entity)) {
                $number = $this->applyNumberPattern($entity, $counter);
            } else {
                $number = $prefix . str_pad($counter, $this->invoice_number_padding, '0', STR_PAD_LEFT);
            }

            // if ($entity == RecurringInvoice::class) {
            //     $number = $this->getSettingsByKey('recurring_invoice_number_prefix')->recurring_invoice_number_prefix . $number;
            // }

            if ($entity_name == Client::class) {
                $check = Client::company($this->company_id)->whereIdNumber($number)->withTrashed()->first();
            } elseif ($entity_name == Invoice::class) {
                $check = Invoice::company($this->company_id)->whereInvoiceNumber($number)->withTrashed()->first();
            } elseif ($entity_name == Quote::class) {
            	$check = Quote::company($this->company_id)->whereQuoteNumber($number)->withTrashed()->first();
            } elseif ($entity_name == Credit::class) {
         		$check = Credit::company($this->company_id)->whereCreditNumber($number)->withTrashed()->first();
            }

            $counter++;
            $counter_offset++; //?

            // prevent getting stuck in a loop
            if ($number == $lastNumber) {
                return '';
            }
            $lastNumber = $number;

		} while ($check);

		$this->incrementCounter($entity);
		
        return $number;

		//increment the counter here
	}

	public function hasSharedCounter() : bool
	{

		return $this->getSettingsByKey('shared_invoice_quote_counter')->shared_invoice_quote_counter === TRUE;

	}

    /**
     * @param $entity
     *
     * @return bool
     */
    public function hasNumberPattern($entity) : bool
    {

        return $this->getNumberPattern($entity) ? true : false;

    }

    /**
     * @param $entity
     *
     * @return NULL|string
     */
    public function getNumberPattern($entity)
    {
        $entity_name = $this->entityName($entity);

		/** Recurring invoice share the same number pattern as invoices  */
		if($entity_name == $this->entityName(RecurringInvoice::class) )
			$entity_name = $this->entityName(Invoice::class);

        $field = $entity_name . "_number_pattern";

		return $this->getSettingsByKey( $field )->{$field};

    }

    /**
     * @param $entity
     *
     * @return string
     */
    public function getNumberPrefix($entity)
    {
        $entity_name = $this->entityName($entity);

        $field = $this->entityName($entity_name) . "_number_prefix";

        return $this->getSettingsByKey( $field )->{$field};

    }




	public function incrementCounter($entity)
	{
		$counter = $this->getCounterName($entity) . '_number_counter';

		//Log::error('entity = '.$entity_name);

		$entity_settings = $this->getSettingsByKey( $counter );

		//Log::error(print_r($entity_settings,1));

		$entity_settings->$counter = $entity_settings->$counter + 1;
		// Log::error('name '.$counter);
		// Log::error('key '.$entity_settings->$counter);
		// Log::error('value '.$entity_settings->{$counter});
		// Log::error('value inc '.$entity_settings->{$counter}++);
		//Log::error($entity_settings->{$counter});
		//Log::error($entity_settings->entity);

		$this->setSettingsByEntity($entity_settings->entity, $entity_settings); 

		//Log::error(print_r($entity_settings,1));


	}

	private function entityName($entity)
	{

		return strtolower(snake_case(class_basename($entity)));
	
	}

	public function getCounter($entity) : int
	{

		$counter = $this->getCounterName($entity) . '_number_counter';

		return $this->getSettingsByKey( $counter )->{$counter};

	}

    /**
     * @param $entity
     * @param mixed $counter
     * todo       localize PHP date
     * @return bool|mixed
     */
    public function applyNumberPattern($entity, $counter = 1)
    {
        $entity_name = $this->entityName($entity);

        $counter = $counter ?: $this->getCounter($entity_name);
        $pattern = $this->getNumberPattern($entity_name);

        if (! $pattern) {
            return false;
        }

        $search = ['{$year}'];
        $replace = [date('Y')];

        $search[] = '{$counter}';
        $replace[] = str_pad($counter, $this->getSettingsByKey( 'counter_padding' )->counter_padding, '0', STR_PAD_LEFT);

        if (strstr($pattern, '{$user_id}')) {
            $user_id = auth()->check() ? auth()->user()->id : 0;
            $search[] = '{$user_id}';
            $replace[] = str_pad(($user_id), 2, '0', STR_PAD_LEFT);
        }

        $matches = false;
        preg_match('/{\$date:(.*?)}/', $pattern, $matches);
        if (count($matches) > 1) {
            $format = $matches[1];
            $search[] = $matches[0];

            /* The following adjusts for the company timezone - may bork tests depending on the time of day the tests are run!!!!!!*/
            $date = Carbon::now($this->company->timezone()->name)->format($format);
            $replace[] = str_replace($format, $date, $matches[1]);
        }

        $pattern = str_replace($search, $replace, $pattern);
        $pattern = $this->getClientInvoiceNumber($pattern, $entity);

        return $pattern;

    }

    private function getClientInvoiceNumber($pattern, $entity)
    {

        $entity_name = $this->entityName($entity);

        if ($entity_name == $this->entityName(Client::class) || $entity_name == $this->entityName(Credit::class) || ! $entity->client_id) 
        {
            return $pattern;
        }

        $search = [
            '{$custom1}',
            '{$custom2}',
            '{$idNumber}',
            '{$clientCustom1}',
            '{$clientCustom2}',
            '{$clientIdNumber}',
            '{$clientCounter}',
        ];

        $counter = $this->getCounterName($entity) . '_number_counter';

        $counter_value = $this->getSettingsByKey( $counter )->{$counter};
        $entity_padding = $this->getSettingsByKey( 'counter_padding' )->counter_padding;

        $replace = [
            $this->custom_value1,
            $this->custom_value2,
            $this->id_number,
            $this->custom_value1, // backwards compatibility
            $this->custom_value2,
            $this->id_number,
            str_pad($counter_value, $entity_padding, '0', STR_PAD_LEFT),
        ];

        return str_replace($search, $replace, $pattern);
    }


    private function getCounterName($entity)
    {

        if($this->entityName($entity) == $this->entityName(RecurringInvoice::class) || ( $this->entityName($entity) == $this->entityName(Quote::class) && $this->hasSharedCounter()) )
            $entity = Invoice::class;

        return $this->entityName($entity);
    }

}