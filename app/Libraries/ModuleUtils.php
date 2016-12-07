<?php namespace App\Libraries;

class ModuleUtils
{
    public static function loadModules()
    {
        $data = [];
        $modules = env('CUSTOM_MODULES');
        $modules = explode(',', $modules);

        foreach ($modules as $module) {
            $info = CurlUtils::get($module . '?action=info');
            if ($info = json_decode($info)) {
                $info->url = $module;
                $data[] = $info;
            }
        }

        session(['custom_modules' => $data]);

        return $data;
    }
}
