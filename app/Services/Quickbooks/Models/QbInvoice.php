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

namespace App\Services\Quickbooks\Models;


use App\Services\Quickbooks\QuickbooksService;

class QbInvoice
{
    
    public function __construct(public QuickbooksService $service)
    {
    }

    public function find(int $id)
    {
        return $this->service->sdk->FindById('Invoice', $id);
    }


}
