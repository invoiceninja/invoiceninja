<?php

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

            if ($entity == RecurringInvoice::class) {
                $number = $this->recurring_invoice_number_prefix . $number;
            }

            if ($entity == Client::class) {
                $check = Client::company($this->company_id)->whereIdNumber($number)->withTrashed()->first();
            } elseif ($entity == Invoice::class) {
                $check = Invoice::company($this->company_id)->whereInvoiceNumber($number)->withTrashed()->first();
            } elseif ($entity == Quote::class) {
            	$check = Quote::company($this->company_id)->whereQuoteNumber($number)->withTrashed()->first();
            } elseif ($entity == Credit::class) {
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


        return $number;

		//increment the counter here
	}

	public function hasSharedCounter() : bool
	{

		return $this->getSettingsByKey('shared_invoice_quote_counter')->shared_invoice_quote_counter;

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

		/** Recurring invoice share the same number pattern as invoices  */
		if($entity == RecurringInvoice::class )
			$entity = Invoice::class;

        $field = $this->entityName($entity) . "_number_pattern";

		return $this->getSettingsByKey( $field )->{$field};

    }

    /**
     * @param $entity
     *
     * @return string
     */
    public function getNumberPrefix($entity)
    {

        $field = $this->entityName($entity) . "_number_prefix";

        return $this->getSettingsByKey( $field )->{$field};

    }


	public function incrementCounter($entity)
	{
		if($entity == RecurringInvoice::class || ( $entity == Quote::class && $this->hasSharedCounter()) )
			$entity = Invoice::class;

		$counter = $this->entityName($entity) . '_number_counter';

		Log::error($counter);

		$entity_settings = $this->getSettingsByKey( $counter );

		Log::error(print_r($entity_settings,1));

		$entity_settings->{$counter} = $entity_settings->{$counter} + 1;

		Log::error($entity_settings->{$counter});
		Log::error($entity_settings->entity);

		$this->setSettingsByEntity($entity_settings->entity, $entity_settings); 

		Log::error(print_r($entity_settings,1));


	}

	private function entityName($entity)
	{

		return strtolower(snake_case(class_basename($entity)));
	
	}

	public function getCounter($entity) : int
	{

		/** Recurring invoice share the same counter as invoices also harvest the invoice_counter if quote and invoices are sharing a counter */
		if($entity == RecurringInvoice::class || ( $entity == Quote::class && $this->hasSharedCounter()) )
			$entity = Invoice::class;

		$counter = $this->entityName($entity) . '_number_counter';

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

        $counter = $counter ?: $this->getCounter($entity);
        $pattern = $this->getNumberPattern($entity);

        if (! $pattern) {
            return false;
        }

        $search = ['{$year}'];
        $replace = [date('Y')];

        $search[] = '{$counter}';
        $replace[] = str_pad($counter, $this->getSettingsByKey( 'counter_padding' )->counter_padding, '0', STR_PAD_LEFT);

        if (strstr($pattern, '{$user_id}')) {
            $user_id = $entity->user ? $entity->user->id : (auth()->check() ? auth()->user()->id : 0);
            $search[] = '{$user_id}';
            $replace[] = str_pad(($user_id + 1), 2, '0', STR_PAD_LEFT);
        }

        $matches = false;
        preg_match('/{\$date:(.*?)}/', $pattern, $matches);
        if (count($matches) > 1) {
            $format = $matches[1];
            $search[] = $matches[0];

            $date = Carbon::now($this->company->timezone()->name)->format($format);
            $replace[] = str_replace($format, $date, $matches[1]);
        }

        $pattern = str_replace($search, $replace, $pattern);
        $pattern = $this->getClientInvoiceNumber($pattern, $entity);

        return $pattern;

    }

}