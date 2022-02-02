<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
namespace App\Import\Providers;

interface ImportInterface
{

    public function import(string $entity);

    public function preTransform(array $data, string $entity_type);

    public function transform(array $data);
}