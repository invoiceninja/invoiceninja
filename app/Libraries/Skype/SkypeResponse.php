<?php

namespace App\Libraries\Skype;

class SkypeResponse
{
    public function __construct($type)
    {
        $this->type = $type;
        $this->attachments = [];
    }

    public static function message($message)
    {
        $instance = new self('message/text');
        $instance->setText($message);

        return json_encode($instance);
    }

    public function setText($text)
    {
        $this->text = $text;
    }

    public function addAttachment($attachment)
    {
        $this->attachments[] = $attachment;
    }
}
