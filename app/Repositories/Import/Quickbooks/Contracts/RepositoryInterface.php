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
namespace App\Repositories\Import\Quickbooks\Contracts;

use Illuminate\Support\Collection;

interface RepositoryInterface
{
    public function get(int $max = 100): Collection;
    public function all(): Collection;
    public function count(): int;
}
