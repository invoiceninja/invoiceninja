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

namespace App\Services\Pdf;

use App\Models\Client;
use App\Models\Invoice;

class PdfMock
{

    public function __construct()
    {

    }

    public function build()
    {

        $mock = Invoice::factory()->make();    
        $mock->client = Client::factory()->make();

        nlog($mock);
        return $mock;

    }

}
