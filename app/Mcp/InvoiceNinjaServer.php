<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mcp;

use Laravel\Mcp\Server;

class InvoiceNinjaServer extends Server
{
    protected string $name = 'Invoice Ninja';
    protected string $version = '1.0.0';
    protected string $instructions = 'Use domain tools with an operation, REST path parameters, query filters, and data. IDs are Invoice Ninja hashed IDs. REST permissions and company isolation apply on every call. GET actions can mutate. Check results before retrying mutations. List data retains REST pagination (query.page, query.per_page).';

    // The complete compact domain catalog fits in one page for clients without pagination.
    public int $defaultPaginationLength = 50;

    protected function boot(): void
    {
        foreach (app(Catalog::class)->operations() as $domain => $operations) {
            $this->tools[] = new DomainTool($domain);
        }
    }
}
