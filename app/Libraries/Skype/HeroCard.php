<?php namespace App\Libraries\Skype;

class HeroCard
{
    public function __construct()
    {
        $this->contentType = 'application/vnd.microsoft.card.hero';
        $this->content = new stdClass;
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

    public function addButton($button)
    {
        $this->content->buttons[] = $button;
    }
}
