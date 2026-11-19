<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\User;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A flutter_settings patch is an object of dot separated paths,
 * so a list or a path with an empty segment is rejected.
 */
class ValidFlutterSettingsPatch implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        if (count($value) > 0 && array_is_list($value)) {
            $fail('The flutter settings must be an object of paths.');

            return;
        }

        foreach (array_keys($value) as $path) {
            if (in_array('', explode('.', (string) $path), true)) {
                $fail('The flutter settings path "' . $path . '" is not valid.');

                return;
            }
        }
    }
}
