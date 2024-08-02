<?php
namespace Omnipay\Rotessa;

use Omnipay\Rotessa\ApiTrait;
use Omnipay\Rotessa\AbstractClient;
use Omnipay\Rotessa\ClientInterface;
use Omnipay\Rotessa\Message\Request\RequestInterface;

class Gateway extends AbstractClient implements ClientInterface {
    
    use ApiTrait;

    protected $default_parameters = ['api_key' => 1234567890 ];

    protected $test_mode = true;

    protected $api_key;

    public function getName()
    {
        return 'Rotessa';
    }

    public function getDefaultParameters() : array
    {
        return array_merge($this->default_parameters, array('api_key' => $this->api_key, 'test_mode' => $this->test_mode ) );
    }

    public function setTestMode($value) {
          $this->test_mode = $value;
    }

    public function getTestMode() {
        return $this->test_mode;
    }

    protected function createRequest($class_name, ?array $parameters = [] ) :RequestInterface {
        $class = null;
        $class_name = "Omnipay\\Rotessa\\Message\\Request\\$class_name";
        $parameters = $class_name::hasModel() ? (($parameters = ($class_name::getModel($parameters)))->validate() ? $parameters->jsonSerialize() : null ) : $parameters;
        try {
          $class = new $class_name($this->httpClient, $this->httpRequest, $this->getDefaultParameters() + $parameters );
        } catch (\Throwable $th) {
          throw $th;
        } 
      
        return $class;
    }

    function setApiKey($value) {
        $this->api_key = $value;
    }

    function getApiKey() {
        return $this->api_key;
    }

    function authorize(array $options = []) : RequestInterface {
        return $this->postCustomers($options);
    }

    function capture(array $options = []) : RequestInterface {
        return array_key_exists('customer_id', $options)? $this->postTransactionSchedules($options) : $this->postTransactionSchedulesCreateWithCustomIdentifier($options)  ;
    }

    function updateCustomer(array $options) : RequestInterface {
        return $this->patchCustomersId($options); 
    }

    function fetchTransaction($id = null) : RequestInterface {
        return $this->getTransactionSchedulesId(compact('id'));
    }

}