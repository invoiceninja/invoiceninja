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

/**
 * Returns a custom translation string
 * falls back on defaults if no string exists.
 *
 * //Cache::forever($custom_company_translated_string, 'mogly');
 *
 * @param string $string
 * @param array $replace
 * @param null $locale
 * @return string
 */
function ctrans(string $string, $replace = [], $locale = null): string
{
    return html_entity_decode(trans($string, $replace, $locale));
}
