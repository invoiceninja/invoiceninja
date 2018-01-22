<?php

namespace App\Libraries;

use HTMLPurifier;
use HTMLPurifier_Config;

class HTMLUtils
{
    public static function sanitizeCSS($css)
    {
        // Allow referencing the body element
        $css = preg_replace('/(?<![a-z0-9\-\_\#\.])body(?![a-z0-9\-\_])/i', '.body', $css);

        //
        // Inspired by http://stackoverflow.com/a/5209050/1721527, dleavitt <https://stackoverflow.com/users/362110/dleavitt>
        //

        // Create a new configuration object
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Filter.ExtractStyleBlocks', true);
        $config->set('CSS.AllowImportant', true);
        $config->set('CSS.AllowTricky', true);
        $config->set('CSS.Trusted', true);
        $config->set('Cache.SerializerPath', base_path('storage/framework/cache'));

        // Create a new purifier instance
        $purifier = new HTMLPurifier($config);

        // Wrap our CSS in style tags and pass to purifier.
        // we're not actually interested in the html response though
        $purifier->purify('<style>'.$css.'</style>');

        // The "style" blocks are stored seperately
        $css = $purifier->context->get('StyleBlocks');

        // Get the first style block
        return count($css) ? $css[0] : '';
    }

    public static function sanitizeHTML($html)
    {
        $html = html_entity_decode($html);

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', base_path('storage/framework/cache'));

        $purifier = new HTMLPurifier($config);

        return $purifier->purify($html);
    }

    public static function previousUrl($fallback)
    {
        $previous = url()->previous();
        $current = request()->url();

        if ($previous == $current) {
            return url($fallback);
        } else {
            return $previous;
        }
    }

    public static function getEnvForAccount($field, $default = '')
    {
        $key = '';

        if ($user = auth()->user()) {
            $key .= $user->account->id . '_';
        }

        $key .= $field;

        return env($key, env($field, $default));
    }
}
