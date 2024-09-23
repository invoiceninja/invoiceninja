<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\Design;

use Closure;
use App\Services\Template\TemplateService;
use Illuminate\Contracts\Validation\ValidationRule;

class TwigLint implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        $ts = new TemplateService();
        $twig = $ts->twig;

        try {
            $twig->parse($twig->tokenize(new \Twig\Source(preg_replace('/<!--.*?-->/s', '', $value), '')));
        } catch (\Twig\Error\SyntaxError $e) {
            $fail($e->getMessage());
        }

    }
}
