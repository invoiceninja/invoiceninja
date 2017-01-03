<?php namespace App\Models\Traits;

use Auth;
use Carbon;
use App\Models\Invoice;
use App\Models\Client;

/**
 * Class GeneratesNumbers
 */
trait GeneratesNumbers
{
    /**
     * @param $invoice
     * @param bool $validateUnique
     * @return mixed|string
     */
    public function getNextInvoiceNumber($invoice, $validateUnique = true)
    {
        if ($this->hasNumberPattern($invoice->invoice_type_id)) {
            $number = $this->getNumberPattern($invoice);
        } else {
            $counter = $this->getCounter($invoice->invoice_type_id);
            $prefix = $this->getNumberPrefix($invoice->invoice_type_id);
            $counterOffset = 0;
            $check = false;

            // confirm the invoice number isn't already taken
            do {
                $number = $prefix . str_pad($counter, $this->invoice_number_padding, '0', STR_PAD_LEFT);
                if ($validateUnique) {
                    $check = Invoice::scope(false, $this->id)->whereInvoiceNumber($number)->withTrashed()->first();
                    $counter++;
                    $counterOffset++;
                }
            } while ($check);

            // update the invoice counter to be caught up
            if ($counterOffset > 1) {
                if ($invoice->isType(INVOICE_TYPE_QUOTE) && !$this->share_counter) {
                    $this->quote_number_counter += $counterOffset - 1;
                } else {
                    $this->invoice_number_counter += $counterOffset - 1;
                }

                $this->save();
            }
        }

        if ($invoice->recurring_invoice_id) {
            $number = $this->recurring_invoice_number_prefix . $number;
        }

        return $number;
    }

    /**
     * @param $invoice_type_id
     * @return string
     */
    public function getNumberPrefix($invoice_type_id)
    {
        if ( ! $this->hasFeature(FEATURE_INVOICE_SETTINGS)) {
            return '';
        }

        return ($invoice_type_id == INVOICE_TYPE_QUOTE ? $this->quote_number_prefix : $this->invoice_number_prefix) ?: '';
    }

    /**
     * @param $invoice_type_id
     * @return bool
     */
    public function hasNumberPattern($invoice_type_id)
    {
        if ( ! $this->hasFeature(FEATURE_INVOICE_SETTINGS)) {
            return false;
        }

        return $invoice_type_id == INVOICE_TYPE_QUOTE ? ($this->quote_number_pattern ? true : false) : ($this->invoice_number_pattern ? true : false);
    }

    /**
     * @param $invoice
     * @return string
     */
    public function hasClientNumberPattern($invoice)
    {
        $pattern = $invoice->invoice_type_id == INVOICE_TYPE_QUOTE ? $this->quote_number_pattern : $this->invoice_number_pattern;

        return strstr($pattern, '$custom');
    }

    /**
     * @param $invoice
     * @return bool|mixed
     */
    public function getNumberPattern($invoice)
    {
        $pattern = $invoice->invoice_type_id == INVOICE_TYPE_QUOTE ? $this->quote_number_pattern : $this->invoice_number_pattern;

        if (!$pattern) {
            return false;
        }

        $search = ['{$year}'];
        $replace = [date('Y')];

        $search[] = '{$counter}';
        $replace[] = str_pad($this->getCounter($invoice->invoice_type_id), $this->invoice_number_padding, '0', STR_PAD_LEFT);

        if (strstr($pattern, '{$userId}')) {
            $search[] = '{$userId}';
            $replace[] = str_pad(($invoice->user->public_id + 1), 2, '0', STR_PAD_LEFT);
        }

        $matches = false;
        preg_match('/{\$date:(.*?)}/', $pattern, $matches);
        if (count($matches) > 1) {
            $format = $matches[1];
            $search[] = $matches[0];
            $date = Carbon::now(session(SESSION_TIMEZONE, DEFAULT_TIMEZONE))->format($format);
            $replace[] = str_replace($format, $date, $matches[1]);
        }

        $pattern = str_replace($search, $replace, $pattern);

        if ($invoice->client_id) {
            $pattern = $this->getClientInvoiceNumber($pattern, $invoice);
        }

        return $pattern;
    }

    /**
     * @param $pattern
     * @param $invoice
     * @return mixed
     */
    private function getClientInvoiceNumber($pattern, $invoice)
    {
        if (!$invoice->client) {
            return $pattern;
        }

        $search = [
            '{$custom1}',
            '{$custom2}',
        ];

        $replace = [
            $invoice->client->custom_value1,
            $invoice->client->custom_value2,
        ];

        return str_replace($search, $replace, $pattern);
    }

    /**
     * @param $invoice_type_id
     * @return mixed
     */
    public function getCounter($invoice_type_id)
    {
        return $invoice_type_id == INVOICE_TYPE_QUOTE && !$this->share_counter ? $this->quote_number_counter : $this->invoice_number_counter;
    }

    /**
     * @param $entityType
     * @return mixed|string
     */
    public function previewNextInvoiceNumber($entityType = ENTITY_INVOICE)
    {
        $invoice = $this->createInvoice($entityType);
        return $this->getNextInvoiceNumber($invoice);
    }

    /**
     * @param $invoice
     */
    public function incrementCounter($invoice)
    {
        // if they didn't use the counter don't increment it
        if ($invoice->invoice_number != $this->getNextInvoiceNumber($invoice, false)) {
            return;
        }

        if ($invoice->isType(INVOICE_TYPE_QUOTE) && !$this->share_counter) {
            $this->quote_number_counter += 1;
        } else {
            $this->invoice_number_counter += 1;
        }

        $this->save();
    }

    public function getNextClientNumber()
    {
        if ( ! $this->hasFeature(FEATURE_INVOICE_SETTINGS)) {
            return '';
        }

        $counter = $this->client_number_counter;
        $prefix = $this->client_number_prefix;
        $counterOffset = 0;
        $check = false;

        // confirm the invoice number isn't already taken
        do {
            if ($this->client_number_pattern) {
                $number = $this->getClientNumberPattern($counter);
            } else {
                $number = $prefix . str_pad($counter, $this->invoice_number_padding, '0', STR_PAD_LEFT);
            }

            $check = Client::scope(false, $this->id)->whereIdNumber($number)->withTrashed()->first();
            $counter++;
            $counterOffset++;
        } while ($check);

        // update the invoice counter to be caught up
        if ($counterOffset > 1) {
            $this->client_number_counter += $counterOffset - 1;
            $this->save();
        }

        return $number;
    }

    private function getClientNumberPattern($counter)
    {
        $pattern = $this->client_number_pattern;

        if ( ! $pattern) {
            return false;
        }

        $search = ['{$year}'];
        $replace = [date('Y')];

        $search[] = '{$counter}';
        $replace[] = str_pad($counter, $this->invoice_number_padding, '0', STR_PAD_LEFT);

        if (strstr($pattern, '{$userId}') && Auth::check()) {
            $search[] = '{$userId}';
            $replace[] = str_pad((Auth::user()->public_id + 1), 2, '0', STR_PAD_LEFT);
        }

        $matches = false;
        preg_match('/{\$date:(.*?)}/', $pattern, $matches);
        if (count($matches) > 1) {
            $format = $matches[1];
            $search[] = $matches[0];
            $date = Carbon::now(session(SESSION_TIMEZONE, DEFAULT_TIMEZONE))->format($format);
            $replace[] = str_replace($format, $date, $matches[1]);
        }

        return str_replace($search, $replace, $pattern);
    }

    public function incrementClientCounter()
    {
        if ($this->client_number_counter) {
            $this->client_number_counter += 1;
            $this->save();
        }
    }

    public function useClientNumbers()
    {
        return $this->hasFeature(FEATURE_INVOICE_SETTINGS) && $this->client_number_counter;
    }
}
