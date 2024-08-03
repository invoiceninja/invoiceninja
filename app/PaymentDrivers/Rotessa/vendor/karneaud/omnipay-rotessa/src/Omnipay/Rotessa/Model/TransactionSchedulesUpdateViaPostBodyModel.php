<?php
namespace Omnipay\Rotessa\Model;

use Omnipay\Rotessa\Model\BaseModel;
use Omnipay\Rotessa\Model\ModelInterface;

class TransactionSchedulesUpdateViaPostBodyModel extends BaseModel implements ModelInterface {

    protected $properties;

    protected $attributes = [
                    "id" => "int", 
                    "amount" => "int", 
                    "comment" => "string", 
            ];
	
    public const DATE_FORMAT = 'Y-m-d H:i:s';

	private $_is_error = false;

	protected $defaults = ["amount" =>0,"comment" =>'0',];

    protected $required = ["amount","comment",];
}
