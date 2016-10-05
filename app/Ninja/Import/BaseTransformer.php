<?php namespace App\Ninja\Import;

use Utils;
use DateTime;
use League\Fractal\TransformerAbstract;

/**
 * Class BaseTransformer
 */
class BaseTransformer extends TransformerAbstract
{
    /**
     * @var
     */
    protected $maps;

    /**
     * BaseTransformer constructor.
     * @param $maps
     */
    public function __construct($maps)
    {
        $this->maps = $maps;
    }

    /**
     * @param $name
     * @return bool
     */
    protected function hasClient($name)
    {
        $name = trim(strtolower($name));
        return isset($this->maps[ENTITY_CLIENT][$name]);
    }

    /**
     * @param $key
     * @return bool
     */
    protected function hasProduct($key)
    {
        $key = trim(strtolower($key));
        return isset($this->maps[ENTITY_PRODUCT][$key]);
    }

    /**
     * @param $data
     * @param $field
     * @return string
     */
    protected function getString($data, $field)
    {
        return (isset($data->$field) && $data->$field) ? $data->$field : '';
    }

    /**
     * @param $data
     * @param $field
     * @return int
     */
    protected function getNumber($data, $field)
    {
        return (isset($data->$field) && $data->$field) ? $data->$field : 0;
    }

    /**
     * @param $name
     * @return null
     */
    protected function getClientId($name)
    {
        $name = strtolower($name);
        return isset($this->maps[ENTITY_CLIENT][$name]) ? $this->maps[ENTITY_CLIENT][$name] : null;
    }

    /**
     * @param $name
     * @return null
     */
    protected function getProductId($name)
    {
        $name = strtolower($name);
        return isset($this->maps[ENTITY_PRODUCT][$name]) ? $this->maps[ENTITY_PRODUCT][$name] : null;
    }

    /**
     * @param $name
     * @return null
     */
    protected function getCountryId($name)
    {
        $name = strtolower($name);
        return isset($this->maps['countries'][$name]) ? $this->maps['countries'][$name] : null;
    }

    /**
     * @param $name
     * @return null
     */
    protected function getCountryIdBy2($name)
    {
        $name = strtolower($name);
        return isset($this->maps['countries2'][$name]) ? $this->maps['countries2'][$name] : null;
    }

    /**
     * @param $name
     * @return mixed
     */
    protected function getFirstName($name)
    {
        $name = Utils::splitName($name);
        return $name[0];
    }

    /**
     * @param $date
     * @param string $format
     * @return null
     */
    protected function getDate($date, $format = 'Y-m-d')
    {
        if ( ! $date instanceof DateTime) {
            $date = DateTime::createFromFormat($format, $date);
        }

        return $date ? $date->format('Y-m-d') : null;
    }

    /**
     * @param $name
     * @return mixed
     */
    protected function getLastName($name)
    {
        $name = Utils::splitName($name);
        return $name[1];
    }

    /**
     * @param $number
     * @return string
     */
    protected function getInvoiceNumber($number)
    {
        $number = strtolower($number);
        return str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param $invoiceNumber
     * @return null
     */
    protected function getInvoiceId($invoiceNumber)
    {
        $invoiceNumber = $this->getInvoiceNumber($invoiceNumber);
        return isset($this->maps[ENTITY_INVOICE][$invoiceNumber]) ? $this->maps[ENTITY_INVOICE][$invoiceNumber] : null;
    }

    /**
     * @param $invoiceNumber
     * @return bool
     */
    protected function hasInvoice($invoiceNumber)
    {
        $invoiceNumber = $this->getInvoiceNumber($invoiceNumber);
        return isset($this->maps[ENTITY_INVOICE][$invoiceNumber]);
    }

    /**
     * @param $invoiceNumber
     * @return null
     */
    protected function getInvoiceClientId($invoiceNumber)
    {
        $invoiceNumber = $this->getInvoiceNumber($invoiceNumber);
        return isset($this->maps[ENTITY_INVOICE.'_'.ENTITY_CLIENT][$invoiceNumber])? $this->maps[ENTITY_INVOICE.'_'.ENTITY_CLIENT][$invoiceNumber] : null;
    }


    /**
     * @param $name
     * @return null
     */
    public function getVendorId($name)
    {
        $name = strtolower($name);
        return isset($this->maps[ENTITY_VENDOR][$name]) ? $this->maps[ENTITY_VENDOR][$name] : null;
    }


    /**
     * @param $name
     * @return null
     */
    public function getExpenseCategoryId($name)
    {
        $name = strtolower($name);
        return isset($this->maps[ENTITY_EXPENSE_CATEGORY][$name]) ? $this->maps[ENTITY_EXPENSE_CATEGORY][$name] : null;
    }

}
