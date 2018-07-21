<?php

namespace App\Ninja\Tickets\Inbound;

class TicketFactory {

    /**
     * @var bool
     */
    public $json;

    /**
     * @var mixed
     */
    public $source;

    /**
     * TicketFactory constructor.
     * @param bool $json
     */
    public function __construct($json = FALSE)

    {
        if(empty($json))
        {
            throw new \Exception('Invalid source');
        }
        
        $this->json = $json;
        $this->source = $this->jsonToArray();
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    private function jsonToArray()
    {
        $this->source = json_decode($this->json, FALSE);

        switch (json_last_error())
        {
            case JSON_ERROR_NONE:
                return $this->source;
                break;
            default:
                throw new \Exception('Postmark Inbound Error: Json format error');
                break;
        }
    }

    /**
     * @param $name
     * @param $arguments
     * @return bool
     */
    public function __call($name, $arguments)
    {
        $name = ucfirst($name);
        return ($this->source->$name) ? $this->source->$name : FALSE;
    }

    /**
     *
     */
    public function to()
    {
        return $this->source->To;
    }

    public function originalRecipient()
    {
        return $this->source->OriginalRecipient;
    }

    /**
     * @return mixed
     */
    public function fromEmail()
    {
        return $this->source->FromFull->Email;
    }

    /**
     * @return string
     */
    public function fromFull()
    {
        return $this->source->FromFull->Name . ' <' . $this->source->FromFull->Email . '>';
    }

    /**
     * @return mixed
     */
    public function fromName()
    {
        return $this->source->FromFull->Name;
    }

    /**
     * @param string $name
     * @return bool|string
     */
    public function headers($name = 'X-Spam-Status')
    {
        foreach ($this->source->Headers as $header)
        {
            if (isset($header->Name) AND $header->Name == $name)
            {
                if ($header->Name == 'Received-SPF')
                {
                    return self::_parseReceivedSpf($header->Value);
                }
                return $header->Value;
            }
        }
        return FALSE;
    }

    /**
     * @param $header
     * @return string
     */
    private static function _parseReceivedSpf($header)
    {
        preg_match_all('/^(\w+\b.*?){1}/', $header, $matches);
        return strtolower($matches[1][0]);
    }

    /**
     * @return array
     */
    public function recipients()
    {
        return self::_parseRecipients($this->source->ToFull);
    }

    /**
     * @return array
     */
    public function undisclosedRecipients()
    {
        return self::_parseRecipients($this->source->CcFull);
    }

    /**
     * @param $recipients
     * @return array
     */
    private static function _parseRecipients($recipients)
    {
        $objects = array_map(function ($object)
        {
            $object = get_object_vars($object);
            if( ! empty($object['Name']))
            {
                $object['Name'] = $object['Name'];
            }
            else
            {
                $object['Name'] = FALSE;
            }
            return (object)$object;
        }, $recipients);
        return $objects;
    }

    /**
     * @return Attachments
     */
    public function attachments()
    {
        return new Attachments($this->source->Attachments);
    }

    /**
     * @return Subject
     */
    public function subject()
    {
        return $this->source->Subject;
    }

    /**
     * @return bool
     */
    public function hasAttachments()
    {
        return count($this->source->Attachments) > 0 ? TRUE : FALSE;
    }
}