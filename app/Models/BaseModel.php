<?php

namespace App\Models;

use Hashids\Hashids;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    public function __call($method, $params)
    {
        $entity = strtolower(class_basename($this));

        if ($entity) {
            $configPath = "modules.relations.$entity.$method";

            if (config()->has($configPath)) {
                $function = config()->get($configPath);

                return $function($this);
            }
        }

        return parent::__call($method, $params);
    }
}
