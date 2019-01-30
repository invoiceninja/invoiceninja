<?php

namespace App\Providers\Traits;


trait HasDynamicConfigs
{
    public function mergeDynamicConfig($path, $key) {
        $key = "modules.{$key}";
        $config = $this->app['config']->get($key, []);

        $this->app['config']->set($key, array_merge_recursive(require $path, $config));
    }
}
