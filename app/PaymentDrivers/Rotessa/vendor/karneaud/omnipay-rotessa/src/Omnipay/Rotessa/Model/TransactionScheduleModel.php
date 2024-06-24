<?php
namespace Omnipay\Rotessa\Model;

use \DateTime;
use Omnipay\Rotessa\Model\BaseModel;
use Omnipay\Rotessa\Object\Frequency;
use Omnipay\Rotessa\Model\ModelInterface;
use Omnipay\Rotessa\Exception\ValidationException;

class TransactionScheduleModel extends BaseModel implements ModelInterface {

    protected $properties;

    protected $attributes = [
                    "id" => "string", 
                    "amount" => "float", 
                    "comment" => "string", 
                    "created_at" => "date", 
                    "financial_transactions" => "array", 
                    "frequency" => "string", 
                    "installments" => "integer", 
                    "next_process_date" => "date", 
                    "process_date" => "date", 
                    "updated_at" => "date", 
                    "customer_id" => "string",
                    "custom_identifier" => "string",
            ];
	
    public const DATE_FORMAT = 'F j, Y';

	protected $defaults = ["amount" =>0.00,"comment" =>' ',"financial_transactions" =>0,"frequency" =>'Once',"installments" =>1];

    protected $required = ["amount","comment","frequency","installments","process_date"];

    public function validate() : bool {
        try {
            parent::validate();
            if(!self::isValidDate($this->process_date)) throw new \Exception("Could not validate date ");
            if(!self::isValidFrequency($this->frequency)) throw new \Exception("Invalid frequency");
            if(is_null($this->customer_id) && is_null($this->custom_identifier)) throw new \Exception("customer id or custom identifier is invalid");
        } catch (\Throwable $th) {
            throw new ValidationException($th->getMessage());
        }

        return true;
    }

    public function jsonSerialize() : array {
        return ['customer_id' => $this->getParameter('customer_id'), 'custom_identifier' => $this->getParameter('custom_identifier') ] + parent::jsonSerialize() ;
    }

    public function __toArray() : array {
        return parent::__toArray() ;
    }

    public function initialize(array $params = [] ) {
        $o_params = array_intersect_key(
            $params = array_intersect_key($params, $this->attributes),
            ($attr = array_filter($this->attributes, fn($p) => $p != "date"))
        );
        parent::initialize($o_params);
        $d_params = array_diff_key($params, $attr);
        array_walk($d_params, function($v,$k) { 
                $this->setParameter($k, self::formatDate( $v) );
            },  );
           
        return $this;
    }

    public static function isValidDate($date) : bool {
        $d = DateTime::createFromFormat(self::DATE_FORMAT, $date);
        // Check if the date is valid and matches the format
        return $d && $d->format(self::DATE_FORMAT) === $date;
    }

    public static function isValidFrequency($value) : bool {
        return Frequency::isValid($value);
    }

    protected static function formatDate($date) : string {
        $d = new DateTime($date);
        return $d->format(self::DATE_FORMAT);
    }
}
