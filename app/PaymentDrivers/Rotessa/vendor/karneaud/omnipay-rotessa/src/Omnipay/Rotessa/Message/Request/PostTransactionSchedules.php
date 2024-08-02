<?php
namespace Omnipay\Rotessa\Message\Request;
// You will need to create this BaseRequest class as abstracted from the AbstractRequest; 
use Omnipay\Rotessa\Message\Request\BaseRequest;
use Omnipay\Rotessa\Message\Request\RequestInterface;

class PostTransactionSchedules extends BaseRequest implements RequestInterface
{
  
  protected $endpoint = '/transaction_schedules';
  protected $method = 'POST';
  protected static $model = 'TransactionSchedule';


  
    public function setCustomerId(string $value) {
    $this->setParameter('customer_id',$value);  
  }
    public function setProcessDate(string $value) {
    $this->setParameter('process_date',$value);  
  }
    public function setFrequency(string $value) {
    $this->setParameter('frequency',$value);  
  }
    public function setInstallments(int $value) {
    $this->setParameter('installments',$value);  
  }
    public function setComment(string $value) {
    $this->setParameter('comment',$value);  
  }
  }
