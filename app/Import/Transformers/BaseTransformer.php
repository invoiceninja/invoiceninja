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

namespace App\Import\Transformers;

use App\Models\ClientContact;
use App\Utils\Number;
use Carbon;
use Exception;

/**
 * Class BaseTransformer.
 */
class BaseTransformer
{
    /**
     * @var
     */
    protected $maps;

    /**
     * BaseTransformer constructor.
     *
     * @param $maps
     */
    public function __construct($maps)
    {
        $this->maps = $maps;
    }

    /**
     * @param $data
     * @param $field
     *
     * @return string
     */
    public function getString($data, $field)
    {
        return (isset($data[$field]) && $data[$field]) ? $data[$field] : '';
    }

    public function getInvoiceTypeId($data, $field)
    {
        return (isset($data[$field]) && $data[$field]) ? $data[$field] : '1';
    }

    public function getCurrencyByCode($data, $key = 'client.currency_id')
    {
        $code = array_key_exists($key, $data) ? $data[$key] : false;

        return $this->maps['currencies'][$code] ?? $this->maps['company']->settings->currency_id;
    }

    public function getClient($client_name, $client_email)
    {
        $clients = $this->maps['company']->clients;

        $client_id_search = $clients->where('id_number', $client_name);

        if ($client_id_search->count() >= 1) {
            return $client_id_search->first()->id;
        }

        $client_name_search = $clients->where('name', $client_name);

        if ($client_name_search->count() >= 1) {
            return $client_name_search->first()->id;
        }

        if (! empty($client_email)) {
            $contacts = ClientContact::where('company_id', $this->maps['company']->id)
                                     ->where('email', $client_email);

            if ($contacts->count() >= 1) {
                return $contacts->first()->client_id;
            }
        }

        return null;
    }

    ///////////////////////////////////////////////////////////////////////////////////
    /**
     * @param $name
     *
     * @return bool
     */
    public function hasClient($name)
    {
        $name = trim(strtolower($name));

        return isset($this->maps['client'][$name]);
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function hasVendor($name)
    {
        $name = trim(strtolower($name));

        return isset($this->maps['vendor'][$name]);
    }

    /**
     * @param $key
     *
     * @return bool
     */
    public function hasProduct($key)
    {
        $key = trim(strtolower($key));

        return isset($this->maps['product'][$key]);
    }

    /**
     * @param $data
     * @param $field
     *
     * @return int
     */
    public function getNumber($data, $field)
    {
        return (isset($data->$field) && $data->$field) ? $data->$field : 0;
    }

    /**
     * @param $data
     * @param $field
     *
     * @return float
     */
    public function getFloat($data, $field)
    {
        if (array_key_exists($field, $data)) {
            $number = preg_replace('/[^0-9-.]+/', '', $data[$field]);
        } else {
            $number = 0;
        }

        return Number::parseFloat($number);
    }

    /**
     * @param $name
     *
     * @return null
     */
    public function getClientId($name)
    {
        $name = strtolower(trim($name));

        return isset($this->maps['client'][$name]) ? $this->maps['client'][$name] : null;
    }

    /**
     * @param $name
     *
     * @return null
     */
    public function getProduct($data, $key, $field, $default = false)
    {
        $productKey = trim(strtolower($data->$key));

        if (! isset($this->maps['product'][$productKey])) {
            return $default;
        }

        $product = $this->maps['product'][$productKey];

        return $product->$field ?: $default;
    }

    /**
     * @param $name
     *
     * @return null
     */
    public function getContact($email)
    {
        $email = trim(strtolower($email));

        if (! isset($this->maps['contact'][$email])) {
            return false;
        }

        return $this->maps['contact'][$email];
    }

    /**
     * @param $name
     *
     * @return null
     */
    public function getCustomer($key)
    {
        $key = trim($key);

        if (! isset($this->maps['customer'][$key])) {
            return false;
        }

        return $this->maps['customer'][$key];
    }

    /**
     * @param $name
     *
     * @return null
     */
    public function getCountryId($name)
    {
        $name = strtolower(trim($name));

        if (strlen($name) == 2) {
            return $this->getCountryIdBy2($name);
        }

        return isset($this->maps['countries'][$name]) ? $this->maps['countries'][$name] : null;
    }

    /**
     * @param $name
     *
     * @return null
     */
    public function getCountryIdBy2($name)
    {
        $name = strtolower(trim($name));

        return isset($this->maps['countries2'][$name]) ? $this->maps['countries2'][$name] : null;
    }

    /**
     * @param $name
     *
     * @return null
     */
    public function getTaxRate($name)
    {
        $name = strtolower(trim($name));

        return isset($this->maps['tax_rates'][$name]) ? $this->maps['tax_rates'][$name] : 0;
    }

    /**
     * @param $name
     *
     * @return null
     */
    public function getTaxName($name)
    {
        $name = strtolower(trim($name));

        return isset($this->maps['tax_names'][$name]) ? $this->maps['tax_names'][$name] : '';
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public function getFirstName($name)
    {
        $name = Utils::splitName($name);

        return $name[0];
    }

    /**
     * @param $date
     * @param string $format
     * @param mixed  $data
     * @param mixed  $field
     *
     * @return null
     */
    public function getDate($data, $field)
    {
        if ($date = data_get($data, $field)) {
            try {
                $date = new Carbon($date);
            } catch (Exception $e) {
                // if we fail to parse return blank
                $date = false;
            }
        }

        return $date ? $date->format('Y-m-d') : null;
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public function getLastName($name)
    {
        $name = Utils::splitName($name);

        return $name[1];
    }

    /**
     * @param $number
     *
     * @return string
     */
    public function getInvoiceNumber($number)
    {
        return $number ? ltrim(trim($number), '0') : null;
    }

    /**
     * @param $invoiceNumber
     *
     * @return null
     */
    public function getInvoiceId($invoiceNumber)
    {
        $invoiceNumber = $this->getInvoiceNumber($invoiceNumber);
        $invoiceNumber = strtolower($invoiceNumber);

        return isset($this->maps['invoice'][$invoiceNumber]) ? $this->maps['invoice'][$invoiceNumber] : null;
    }

    /**
     * @param $invoiceNumber
     *
     * @return null
     */
    public function getInvoicePublicId($invoiceNumber)
    {
        $invoiceNumber = $this->getInvoiceNumber($invoiceNumber);
        $invoiceNumber = strtolower($invoiceNumber);

        return isset($this->maps['invoice'][$invoiceNumber]) ? $this->maps['invoices'][$invoiceNumber]->public_id : null;
    }

    /**
     * @param $invoiceNumber
     *
     * @return bool
     */
    public function hasInvoice($invoiceNumber)
    {
        $invoiceNumber = $this->getInvoiceNumber($invoiceNumber);
        $invoiceNumber = strtolower($invoiceNumber);

        return $this->maps['invoice'][$invoiceNumber] ?? null;
    }

    /**
     * @param $invoiceNumber
     *
     * @return null
     */
    public function getInvoiceClientId($invoiceNumber)
    {
        $invoiceNumber = $this->getInvoiceNumber($invoiceNumber);
        $invoiceNumber = strtolower($invoiceNumber);

        return $this->maps['invoice_client'][$invoiceNumber] ?? null;
    }

    /**
     * @param $name
     *
     * @return null
     */
    public function getVendorId($name)
    {
        $name = strtolower(trim($name));

        return $this->maps['vendor'][$name] ?? null;
    }

    /**
     * @param $name
     *
     * @return null
     */
    public function getExpenseCategoryId($name)
    {
        $name = strtolower(trim($name));

        return $this->maps['expense_category'][$name] ?? null;
    }

    /**
     * @param $name
     *
     * @return null
     */
    public function getProjectId($name)
    {
        $name = strtolower(trim($name));

        return $this->maps['project'][$name] ?? null;
    }

    /**
     * @param $name
     *
     * @return null
     */
    public function getPaymentTypeId($name)
    {
        $name = strtolower(trim($name));

        return $this->maps['payment_type'][$name] ?? null;
    }
}
