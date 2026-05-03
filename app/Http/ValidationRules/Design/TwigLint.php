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

namespace App\Http\ValidationRules\Design;

use Closure;
use App\Services\Template\TemplateService;
use Illuminate\Contracts\Validation\ValidationRule;

class TwigLint implements ValidationRule
{
    /** @var array<string, string> */
    private array $syntaxMessages = [
        '/Unexpected token "end of print statement" \("name" expected\)/' => 'A filter name is missing after the "|" pipe character on line :line.',
        '/Unexpected token "punctuation" of value "\(" \("end of print statement" expected\)/' => 'Unexpected "(" on line :line. You may be calling a function that does not exist.',
        '/Unknown "(\w+)" tag/' => 'Unknown tag ":match" on line :line. Check for typos.',
        '/Unexpected "(\w+)" tag/' => 'Unexpected ":match" tag on line :line. A previous tag may not have been closed.',
        '/Unclosed "(\w+)"/' => 'Unclosed ":match" tag on line :line. Add the matching closing tag.',
        '/Unexpected token "end of template"/' => 'Unexpected end of template on line :line. A tag or expression may not have been closed.',
        '/The block "(\w+)" has already been defined/' => 'The block ":match" is defined more than once on line :line.',
        '/Unexpected token "operator" of value "="/' => 'Unexpected "=" on line :line. Use the "set" tag to assign values.',
        '/Value for argument "(\w+)" is required for filter "(\w+)"/' => 'The ":match2" filter requires a ":match1" argument on line :line.',
        '/Unknown "(\w+)" filter/' => 'Unknown filter ":match" on line :line. Check for typos.',
        '/Unknown "(\w+)" function/' => 'Unknown function ":match" on line :line. Check for typos.',
    ];

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        $ts = new TemplateService();
        $twig = $ts->twig;

        $template = preg_replace('/<!--.*?-->/s', '', $value ?? '');

        // Pass 1: syntax check
        try {
            $twig->parse($twig->tokenize(new \Twig\Source($template, '')));
        } catch (\Twig\Error\SyntaxError $e) {
            $fail($this->humanizeSyntaxError($e));
            return;
        }

        // Pass 2: sandbox security policy check
        try {
            $twig->createTemplate($template);
        } catch (\Twig\Sandbox\SecurityNotAllowedFilterError $e) {
            $fail('Filter "' . $e->getFilterName() . '" is not allowed.');
            return;
        } catch (\Twig\Sandbox\SecurityNotAllowedTagError $e) {
            $fail('Tag "' . $e->getTagName() . '" is not allowed.');
            return;
        } catch (\Twig\Sandbox\SecurityNotAllowedFunctionError $e) {
            $fail('Function "' . $e->getFunctionName() . '" is not allowed.');
            return;
        } catch (\Twig\Sandbox\SecurityError $e) {
            $fail($e->getMessage());
            return;
        } catch (\Twig\Error\SyntaxError $e) {
            $fail($this->humanizeSyntaxError($e));
            return;
        }

        // Pass 3: mock render with real data to catch runtime errors
        $design = request()->input('design');

        if (is_array($design) && isset($design['design'])) {
            try {
                /** @var \App\Models\User $user */
                $user = auth()->user();
                /** @var \App\Models\Company $company */
                $company = $user->company();

                (new TemplateService())
                    ->setCompany($company)
                    ->setTemplate($design)
                    ->mock();
            } catch (\Twig\Sandbox\SecurityError $e) {
                $fail($e->getMessage());
            } catch (\Twig\Error\SyntaxError $e) {
                $fail($this->humanizeSyntaxError($e));
            } catch (\Twig\Error\RuntimeError $e) {
                $fail($this->humanizeRuntimeError($e));
            } catch (\Twig\Error\Error $e) {
                $fail("Template error on line {$e->getTemplateLine()}: {$e->getRawMessage()}");
            }
        }

    }

    private function humanizeSyntaxError(\Twig\Error\SyntaxError $e): string
    {
        $raw = $e->getRawMessage();
        $line = $e->getTemplateLine();

        foreach ($this->syntaxMessages as $pattern => $message) {
            if (preg_match($pattern, $raw, $matches)) {
                $message = str_replace(':line', (string) $line, $message);
                if (isset($matches[2])) {
                    $message = str_replace(':match2', $matches[2], $message);
                }
                if (isset($matches[1])) {
                    $message = str_replace(':match1', $matches[1], $message);
                    $message = str_replace(':match', $matches[1], $message);
                }
                return $message;
            }
        }

        return "Template syntax error on line {$line}: {$raw}";
    }

    private function humanizeRuntimeError(\Twig\Error\RuntimeError $e): string
    {
        $raw = $e->getRawMessage();
        $line = $e->getTemplateLine();

        if (preg_match('/expects a sequence\/mapping or "Traversable", got "(\w+)"/', $raw, $matches)) {
            return "The filter on line {$line} expects a list or object, but received a {$matches[1]}. Check that you are applying list filters (filter, map, sort, etc.) to list variables.";
        }

        if (preg_match('/Key "(\w+)" does not exist/', $raw, $matches)) {
            return "Variable \"{$matches[1]}\" does not exist on line {$line}. Check for typos or make sure the variable is available.";
        }

        return "Template error on line {$line}: {$raw}";
    }
}
