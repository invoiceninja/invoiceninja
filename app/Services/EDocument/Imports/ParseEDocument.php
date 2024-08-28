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

use App\Models\Company;
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
    public function __construct(private UploadedFile $file, private Company $company)
    {

    }

    /**
     * Execute the service.
     * the service will parse the file with all available libraries of the system and will return an expense, when possible
     *
     * @developer the function should be implemented with local first aproach to save costs of external providers (like mindee ocr)
     *
     * @return Expense
     * @throws \Exception
     */
    public function run(): Expense
    {

        /** @var \App\Models\Account $account */
        $account = $this->company->owner()->account;

        // ZUGFERD - try to parse via Zugferd lib
        switch (true) {
            case ($this->file->getExtension() == 'pdf' || $this->file->getMimeType() == 'application/pdf'):
            case ($this->file->getExtension() == 'xml' || $this->file->getMimeType() == 'application/xml') && stristr($this->file->get(), "urn:cen.eu:en16931:2017"):
            case ($this->file->getExtension() == 'xml' || $this->file->getMimeType() == 'application/xml') && stristr($this->file->get(), "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0"):
            case ($this->file->getExtension() == 'xml' || $this->file->getMimeType() == 'application/xml') && stristr($this->file->get(), "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_2.1"):
            case ($this->file->getExtension() == 'xml' || $this->file->getMimeType() == 'application/xml') && stristr($this->file->get(), "urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_2.0"):
                try {
                    return (new ZugferdEDocument($this->file, $this->company))->run();
                } catch (Exception $e) {
                    nlog("Zugferd Exception: " . $e->getMessage());
                }
        }

        // MINDEE OCR - try to parse via mindee external service
        if (config('services.mindee.api_key') && !(Ninja::isHosted() && !($account->isPaid() && $account->plan == 'enterprise')))
            try {
                return (new MindeeEDocument($this->file))->run();
            } catch (Exception $e) {
                if (!($e->getMessage() == 'Unsupported document type'))
                    nlog("Mindee Exception: " . $e->getMessage());
            }

        // NO PARSER OR ERROR
        throw new Exception("File type not supported or issue while parsing", 409);
    }
}

