<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\DataMapper;

use Parsedown;

class EmailTemplateDefaults
{
    public static function emailInvoiceSubject()
    {
        return Parsedown::instance()->line(self::transformText('invoice_subject'));
    }

    public static function emailInvoiceTemplate()
    {
        return Parsedown::instance()->line(self::transformText('invoice_message'));
    }

    private static function transformText($string)
    {
        return str_replace(":", "$", ctrans('texts.'.$string));
    }
}
