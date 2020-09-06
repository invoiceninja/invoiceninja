<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Designs;

abstract class AbstractDesign
{
    abstract public function includes();

    abstract public function header();

    abstract public function body();

    abstract public function product();

    abstract public function task();

    abstract public function footer();
}
