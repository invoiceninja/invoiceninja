<?php namespace App\Libraries\Skype;

class ButtonCard
{
    public function __construct($type, $title, $value)
    {
        $this->type = $type;
        $this->title = $title;
        $this->value = $value;
    }
}
