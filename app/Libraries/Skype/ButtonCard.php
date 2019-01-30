<?php

namespace App\Libraries\Skype;

class ButtonCard
{
    public function __construct($type, $title, $value, $url = false)
    {
        $this->type = $type;
        $this->title = $title;
        $this->value = $value;
        $this->image = $url;
    }
}
