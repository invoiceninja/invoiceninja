<?php

namespace App\Libraries\Skype;

use stdClass;

class HeroCard
{
    public function __construct()
    {
        $this->contentType = 'application/vnd.microsoft.card.hero';
        $this->content = new stdClass();
        $this->content->buttons = [];
    }

    public function setTitle($title)
    {
        $this->content->title = $title;
    }

    public function setSubitle($subtitle)
    {
        $this->content->subtitle = $subtitle;
    }

    public function setText($text)
    {
        $this->content->text = $text;
    }

    public function addButton($type, $title, $value, $url = false)
    {
        $this->content->buttons[] = new ButtonCard($type, $title, $value, $url);
    }
}
