<?php

namespace App\Libraries\Skype;

class CarouselCard
{
    public function __construct()
    {
        $this->contentType = 'application/vnd.microsoft.card.carousel';
        $this->attachments = [];
    }

    public function addAttachment($attachment)
    {
        $this->attachments[] = $attachment;
    }
}
