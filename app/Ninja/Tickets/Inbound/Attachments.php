<?php

namespace App\Ninja\Tickets\Inbound;

Class Attachments extends TicketFactory  implements \Iterator {


    /**
     * @var Attachments
     */
    protected $attachments;

    /**
     * Attachments constructor.
     * @param bool $attachments
     */
    public function __construct($attachments)
    {
        $this->attachments = $attachments;
        $this->position = 0;
    }

    /**
     * @param $key
     * @return Attachment|bool
     */
    function get($key) {
        $this->position = $key;
        if( ! empty($this->attachments[$key]))
        {
            return new Attachment($this->attachments[$key]);
        }
        else
        {
            return FALSE;
        }
    }

    /**
     *
     */
    function rewind()
    {
        $this->position = 0;
    }

    /**
     * @return Attachment
     */
    function current()
    {
        return new Attachment($this->attachments[$this->position]);
    }

    /**
     * @return int
     */
    function key()
    {
        return $this->position;
    }

    /**
     *
     */
    function next()
    {
        ++$this->position;
    }

    /**
     * @return bool
     */
    function valid()
    {
        return isset($this->attachments[$this->position]);
    }
}