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

namespace App\Services\EDocument\Imports;

use Exception;
use App\Utils\Ninja;
use App\Models\Company;
use App\Models\Expense;
use App\Services\AbstractService;
use Illuminate\Http\UploadedFile;

class ParseEDocument extends AbstractService
{
    /**
     * @throws Exception
     */
    public function __construct(private UploadedFile $file, private Company $company) {}

    /**
     * Execute the service.
     * the service will parse the file with all available libraries of the system and will return an expense, when possible
     *
     * @developer the function should be implemented with local first aproach to save costs of external providers (like mindee ocr)
     *
     * @return Expense
     * @throws \Throwable
     */
    public function run(): Expense
    {
        /** @var \App\Models\Account $account */
        $account = $this->company->owner()->account;

        $content = $this->file->get();
        $extension = strtolower($this->file->getClientOriginalExtension() ?: $this->file->getExtension());
        $mimetype = strtolower($this->file->getClientMimeType() ?: $this->file->getMimeType());
        $isPdf = $extension === 'pdf' || $mimetype === 'application/pdf';
        $isXml = $extension === 'xml'
            || in_array($mimetype, ['application/xml', 'text/xml'], true)
            || str_ends_with($mimetype, '+xml');

        if ($isPdf || $isXml) {
            try {
                (new FacturXXmlExtractor())->extract($content);

                return (new ZugferdEDocument($this->file, $this->company))->run();
            } catch (\Throwable $e) {
                nlog('Factur-X/ZUGFeRD import exception: ' . $e->getMessage());
            }
        }

        if ($isXml) {
            try {
                return (new UblEDocument($this->file, $this->company))->run();
            } catch (\Throwable $e) {
                nlog('UBL import exception: ' . $e->getMessage());
            }
        }

        // MINDEE OCR - try to parse via mindee external service
        if (config('services.mindee.api_key') && !(Ninja::isHosted() && !($account->isPaid() && $account->plan == 'enterprise'))) {
            switch (true) {
                case ($extension == 'pdf' || $mimetype == 'application/pdf'):
                case ($extension == 'heic' || $extension == 'heic' || $extension == 'png' || $extension == 'jpg' || $extension == 'jpeg' || $extension == 'webp' || str_starts_with($mimetype, 'image/')):
                    try {
                        return (new MindeeEDocument($this->file, $this->company))->run();
                    } catch (Exception $e) {
                        if (!($e->getMessage() == 'Unsupported document type')) {
                            nlog("Mindee Exception: " . $e->getMessage());
                        }
                    }
            }
        }

        // NO PARSER OR ERROR
        throw new Exception("File type not supported or issue while parsing", 409);
    }
}
