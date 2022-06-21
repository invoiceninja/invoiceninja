<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Providers;

interface ImportInterface
{
    public function import(string $entity);

    public function transform(array $data);

    public function client();

    public function product();

    public function invoice();

    public function payment();

    public function vendor();

    public function expense();
}
