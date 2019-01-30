<?php

namespace App\Ninja\Import;

use Carbon;
use League\Fractal\TransformerAbstract;
use Utils;
use Exception;

/**
 * Class BaseTransformer.
 */
class BaseTransformer extends TransformerAbstract
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
     * @param $name
     *
     * @return bool
     */
    public function hasClient($name)
    {
        $name = trim(strtolower($name));

        return isset($this->maps[ENTITY_CLIENT][$name]);
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function hasVendor($name)
    {
        $name = trim(strtolower($name));

        return isset($this->maps[ENTITY_VENDOR][$name]);
    }


    /**
     * @param $key
     *
     * @return bool
     */
    public function hasProduct($key)
    {
        $key = trim(strtolower($key));

        return isset($this->maps[ENTITY_PRODUCT][$key]);
    }

    /**
     * @param $data
     * @param $field
     *
     * @return string
     */
    public function getString($data, $field)
    {
        return (isset($data->$field) && $data->$field) ? $data->$field : '';
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
        return (isset($data->$field) && $data->$field) ? Utils::parseFloat($data->$field) : 0;
    }

    /**
     * @param $name
     *
     * @return null
     */
    public function getClientId($name)
    {
        $name = strtolower(trim($name));

        return isset($this->maps[ENTITY_CLIENT][$name]) ? $this->maps[ENTITY_CLIENT][$name] : null;
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
        return $number ? str_pad(trim($number), 4, '0', STR_PAD_LEFT) : null;
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
        return isset($this->maps[ENTITY_INVOICE][$invoiceNumber]) ? $this->maps[ENTITY_INVOICE][$invoiceNumber] : null;
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
        return isset($this->maps['invoices'][$invoiceNumber]) ? $this->maps['invoices'][$invoiceNumber]->public_id : null;
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

        return isset($this->maps[ENTITY_INVOICE][$invoiceNumber]);
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

        return isset($this->maps[ENTITY_INVOICE.'_'.ENTITY_CLIENT][$invoiceNumber]) ? $this->maps[ENTITY_INVOICE.'_'.ENTITY_CLIENT][$invoiceNumber] : null;
    }

    /**
     * @param $name
     *
     * @return null
     */
    public function getVendorId($name)
    {
        $name = strtolower(trim($name));

        return isset($this->maps[ENTITY_VENDOR][$name]) ? $this->maps[ENTITY_VENDOR][$name] : null;
    }

    /**
     * @param $name
     *
     * @return null
     */
    public function getExpenseCategoryId($name)
    {
        $name = strtolower(trim($name));

        return isset($this->maps[ENTITY_EXPENSE_CATEGORY][$name]) ? $this->maps[ENTITY_EXPENSE_CATEGORY][$name] : null;
    }
}
