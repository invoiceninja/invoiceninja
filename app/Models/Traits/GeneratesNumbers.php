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
     * @param $entity
     * @return mixed|string
     */
    public function getNextNumber($entity = false)
    {
        $entity = $entity ?: new Client();
        $entityType = $entity->getEntityType();

        $counter = $this->getCounter($entityType);
        $prefix = $this->getNumberPrefix($entityType);
        $counterOffset = 0;
        $check = false;

        if ($entityType == ENTITY_CLIENT && ! $this->clientNumbersEnabled()) {
            return '';
        }

        // confirm the invoice number isn't already taken
        do {
            if ($this->hasNumberPattern($entityType)) {
                $number = $this->applyNumberPattern($entity, $counter);
            } else {
                $number = $prefix . str_pad($counter, $this->invoice_number_padding, '0', STR_PAD_LEFT);
            }

            if ($entity->isEntityType(ENTITY_CLIENT)) {
                $check = Client::scope(false, $this->id)->whereIdNumber($number)->withTrashed()->first();
            } else {
                $check = Invoice::scope(false, $this->id)->whereInvoiceNumber($number)->withTrashed()->first();
            }
            $counter++;
            $counterOffset++;
        } while ($check);

        // update the counter to be caught up
        if ($counterOffset > 1) {
            if ($entity->isEntityType(ENTITY_CLIENT)) {
                if ($this->clientNumbersEnabled()) {
                    $this->client_number_counter += $counterOffset - 1;
                    $this->save();
                }
            } elseif ($entity->isType(INVOICE_TYPE_QUOTE)) {
                if (! $this->share_counter) {
                    $this->quote_number_counter += $counterOffset - 1;
                    $this->save();
                }
            } else {
                $this->invoice_number_counter += $counterOffset - 1;
                $this->save();
            }
        }

        if ($entity->recurring_invoice_id) {
            $number = $this->recurring_invoice_number_prefix . $number;
        }

        return $number;
    }

    /**
     * @param $entityType
     * @return string
     */
    public function getNumberPrefix($entityType)
    {
        if (! $this->hasFeature(FEATURE_INVOICE_SETTINGS)) {
            return '';
        }

        $field = "{$entityType}_number_prefix";
        return $this->$field ?: '';
    }

    /**
     * @param $entityType
     * @return bool
     */
    public function getNumberPattern($entityType)
    {
        if (! $this->hasFeature(FEATURE_INVOICE_SETTINGS)) {
            return false;
        }

        $field = "{$entityType}_number_pattern";
        return $this->$field;
    }

    /**
     * @param $entityType
     * @return bool
     */
    public function hasNumberPattern($entityType)
    {
        return $this->getNumberPattern($entityType) ? true : false;
    }

    /**
     * @param $entityType
     * @return string
     */
    public function hasClientNumberPattern($invoice)
    {
        $pattern = $invoice->invoice_type_id == INVOICE_TYPE_QUOTE ? $this->quote_number_pattern : $this->invoice_number_pattern;

        return strstr($pattern, '$custom') || strstr($pattern, '$idNumber');
    }

    /**
     * @param $entity
     * @return bool|mixed
     */
    public function applyNumberPattern($entity, $counter = 0)
    {
        $entityType = $entity->getEntityType();
        $counter = $counter ?: $this->getCounter($entityType);
        $pattern = $this->getNumberPattern($entityType);

        if (!$pattern) {
            return false;
        }

        $search = ['{$year}'];
        $replace = [date('Y')];

        $search[] = '{$counter}';
        $replace[] = str_pad($counter, $this->invoice_number_padding, '0', STR_PAD_LEFT);

        if (strstr($pattern, '{$userId}')) {
            $userId = $entity->user ? $entity->user->public_id : (Auth::check() ? Auth::user()->public_id : 0);
            $search[] = '{$userId}';
            $replace[] = str_pad(($userId + 1), 2, '0', STR_PAD_LEFT);
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

        if ($entity->client_id) {
            $pattern = $this->getClientInvoiceNumber($pattern, $entity);
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
            '{$idNumber}',
        ];

        $replace = [
            $invoice->client->custom_value1,
            $invoice->client->custom_value2,
            $invoice->client->id_number,
        ];

        return str_replace($search, $replace, $pattern);
    }

    /**
     * @param $entityType
     * @return mixed
     */
    public function getCounter($entityType)
    {
        if ($entityType == ENTITY_CLIENT) {
            return $this->client_number_counter;
        } elseif ($entityType == ENTITY_QUOTE && ! $this->share_counter) {
            return $this->quote_number_counter;
        } else {
            return $this->invoice_number_counter;
        }
    }

    /**
     * @param $entityType
     * @return mixed|string
     */
    public function previewNextInvoiceNumber($entityType = ENTITY_INVOICE)
    {
        $invoice = $this->createInvoice($entityType);
        return $this->getNextNumber($invoice);
    }

    /**
     * @param $entity
     */
    public function incrementCounter($entity)
    {
        if ($entity->isEntityType(ENTITY_CLIENT)) {
            if ($this->client_number_counter) {
                $this->client_number_counter += 1;
            }
        } elseif ($entity->isType(INVOICE_TYPE_QUOTE) && ! $this->share_counter) {
            $this->quote_number_counter += 1;
        } else {
            $this->invoice_number_counter += 1;
        }

        $this->save();
    }

    public function clientNumbersEnabled()
    {
        return $this->hasFeature(FEATURE_INVOICE_SETTINGS) && $this->client_number_counter;
    }
}
