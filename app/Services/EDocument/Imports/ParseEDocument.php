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

namespace App\Services\EDocument\Imports;

use App\Models\Expense;
use App\Services\AbstractService;
use Exception;

class ParseEDocument extends AbstractService
{

    /**
     * @throws Exception
     */
    public function __construct(private string $file_content, private string $file_name)
    {

    }

    /**
     * Execute the service.
     *
     * @return Expense
     * @throws \Exception
     */
    public function run(): Expense
    {
        if (str_contains($this->file_name, ".xml")) {
            switch (true) {
                case stristr($this->file_content, "urn:cen.eu:en16931:2017"):
                case stristr($this->file_content, "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0"):
                case stristr($this->file_content, "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_2.1"):
                case stristr($this->file_content, "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_2.0"):
                    return (new ZugferdEDocument($this->file_content, $this->file_name))->run();
                default:
                    throw new Exception("E-Invoice standard not supported");
            }
        } else {
            throw new Exception("File type not supported");
        }
    }
}

