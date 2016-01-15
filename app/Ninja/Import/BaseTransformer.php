<?php namespace App\Ninja\Import;

use Utils;
use DateTime;
use League\Fractal\TransformerAbstract;

class BaseTransformer extends TransformerAbstract
{
    protected $maps;

    public function __construct($maps)
    {
        $this->maps = $maps;
    }

    protected function hasClient($name)
    {
        $name = strtolower($name);
        return isset($this->maps[ENTITY_CLIENT][$name]);
    }

    protected function getString($data, $field)
    {
        return (isset($data->$field) && $data->$field) ? $data->$field : '';
    }

    protected function getClientId($name)
    {
        $name = strtolower($name);
        return isset($this->maps[ENTITY_CLIENT][$name]) ? $this->maps[ENTITY_CLIENT][$name] : null;
    }

    protected function getCountryId($name)
    {
        $name = strtolower($name);
        return isset($this->maps['countries'][$name]) ? $this->maps['countries'][$name] : null;
    }

    protected function getCountryIdBy2($name)
    {
        $name = strtolower($name);
        return isset($this->maps['countries2'][$name]) ? $this->maps['countries2'][$name] : null;
    }

    protected function getFirstName($name)
    {
        $name = Utils::splitName($name);
        return $name[0];
    }

    protected function getDate($date, $format = 'Y-m-d')
    {
        if ( ! $date instanceof DateTime) {
            $date = DateTime::createFromFormat($format, $date);
        }
        
        return $date ? $date->format('Y-m-d') : null;
    }

    protected function getLastName($name)
    {
        $name = Utils::splitName($name);
        return $name[1];
    }

    protected function getInvoiceNumber($number)
    {
        $number = strtolower($number);
        return str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    protected function getInvoiceId($invoiceNumber)
    {
        $invoiceNumber = $this->getInvoiceNumber($invoiceNumber);
        return isset($this->maps[ENTITY_INVOICE][$invoiceNumber]) ? $this->maps[ENTITY_INVOICE][$invoiceNumber] : null;
    }

    protected function hasInvoice($invoiceNumber)
    {
        $invoiceNumber = $this->getInvoiceNumber($invoiceNumber);
        return isset($this->maps[ENTITY_INVOICE][$invoiceNumber]);
    }

    protected function getInvoiceClientId($invoiceNumber)
    {
        $invoiceNumber = $this->getInvoiceNumber($invoiceNumber);
        return isset($this->maps[ENTITY_INVOICE.'_'.ENTITY_CLIENT][$invoiceNumber])? $this->maps[ENTITY_INVOICE.'_'.ENTITY_CLIENT][$invoiceNumber] : null;
    }

    
    protected function getVendorId($name)
    {
        $name = strtolower($name);
        return isset($this->maps[ENTITY_VENDOR][$name]) ? $this->maps[ENTITY_VENDOR][$name] : null;
    }
    
}