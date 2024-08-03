<?php

namespace Omnipay\Rotessa\Http\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

class Response extends JsonResponse
{

    protected $reason_phrase = '';
    protected $reason_code = '';

    public function __construct(mixed $data = null, int $status = 200, array $headers = [])
    {
        
       parent::__construct($data , $status, $headers, true);
      
        if(array_key_exists('errors',$data = json_decode( $this->content, true) )) {
            $data = $data['errors'][0];
            $this->reason_phrase = $data['error_message'] ;
            $this->reason_code = $data['error_message'] ;
        }
    }

    public function getReasonPhrase()  {
       return $this->reason_phrase;
    }

    public function getReasonCode() {
       return $this->reason_code;
    }
}
