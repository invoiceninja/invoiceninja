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
use App\Utils\Ninja;
use Exception;
use Illuminate\Http\UploadedFile;

class ParseEDocument extends AbstractService
{

    /**
     * @throws Exception
     */
    public function __construct(private UploadedFile $file)
    {

    }

    /**
     * Execute the service.
     * the service will parse the file with all available libraries of the system and will return an expense, when possible
     *
     * @return Expense
     * @throws \Exception
     */
    public function run(): Expense
    {

        /** @var \App\Models\Account $account */
        $account = auth()->user()->account;

        // try to parse via Zugferd lib
        $zugferd_exception = null;
        try {
            switch (true) {
                case $this->file->getExtension() == 'pdf':
                case $this->file->getExtension() == 'xml' && stristr($this->file->get(), "urn:cen.eu:en16931:2017"):
                case $this->file->getExtension() == 'xml' && stristr($this->file->get(), "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0"):
                case $this->file->getExtension() == 'xml' && stristr($this->file->get(), "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_2.1"):
                case $this->file->getExtension() == 'xml' && stristr($this->file->get(), "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_2.0"):
                    return (new ZugferdEDocument($this->file))->run();
            }
        } catch (Exception $e) {
            $zugferd_exception = $e;
        }

        // try to parse via mindee lib
        $mindee_exception = null;
        if (config('services.mindee.api_key') && (Ninja::isSelfHost() || (Ninja::isHosted() && $account->isPaid() && $account->plan == 'enterprise')))
            try {
                return (new MindeeEDocument($this->file))->run();
            } catch (Exception $e) {
                // ignore not available exceptions
                $mindee_exception = $e;
            }

        // log exceptions and throw error
        if ($zugferd_exception)
            nlog("Zugferd Exception: " . $zugferd_exception->getMessage());
        if ($mindee_exception)
            nlog("Mindee Exception: " . $mindee_exception->getMessage());
        throw new Exception("File type not supported or issue while parsing", 409);
    }
}

