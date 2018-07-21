<?php

namespace App\Ninja\Tickets\Inbound;

Class Attachment extends TicketFactory {


    /**
     * @var Attachment
     */
    protected $attachment;

    /**
     * Attachment constructor.
     * @param bool $attachment
     */
    public function __construct($attachment)
    {
        $this->attachment = $attachment;
        $this->name = $this->attachment->name;
        $this->contentType = $this->attachment->contentType;
        $this->contentLength = $this->attachment->contentLength;
        $this->content = $this->attachment->content;
    }

    /**
     * @return string
     */
    private function _read()
    {
        return base64_decode(chunk_split($this->attachment->Content));
    }

    /**
     * @param $directory
     */
    public function download($directory)
    {
        file_put_contents($directory . $this->name, $this->_read());
    }

}