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

        $expense = null;

        // try to parse via Zugferd lib
        $zugferd_exception = null;
        try {
            $expense = (new ZugferdEDocument($this->file))->run();
        } catch (Exception $e) {
            $zugferd_exception = $e;
        }

        // try to parse via mindee lib
        $mindee_exception = null;
        try {
            $expense = (new MindeeEDocument($this->file))->run();
        } catch (Exception $e) {
            // ignore not available exceptions
            $mindee_exception = $e;
        }

        // return expense, when available and supress any errors occured before
        if ($expense)
            return $expense;

        // log exceptions and throw error
        if ($zugferd_exception)
            nlog("Zugferd Exception: " . $zugferd_exception->getMessage());
        if ($mindee_exception)
            nlog("Mindee Exception: " . $zugferd_exception->getMessage());
        throw new Exception("File type not supported or issue while parsing");
    }
}

