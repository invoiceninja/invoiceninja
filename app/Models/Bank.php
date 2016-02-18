<?php namespace App\Models;

use Eloquent;

class Bank extends Eloquent
{
    public $timestamps = false;

    public function getOFXBank($finance)
    {
        $config = json_decode($this->config);

        return new \App\Libraries\Bank($finance, $config->fid, $config->url, $config->org);
    }
}
