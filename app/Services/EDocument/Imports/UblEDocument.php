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
use App\Factory\ExpenseFactory;
use App\Services\AbstractService;
use Illuminate\Http\UploadedFile;
use App\Utils\Traits\SavesDocuments;

class UblEDocument extends AbstractService
{
    use SavesDocuments;

    /**
     * @throws \Throwable
     */
    public function __construct(public UploadedFile $file, public Company $company)
    {
        # curl -X POST http://localhost:8000/api/v1/edocument/upload -H "Content-Type: multipart/form-data" -H "X-API-TOKEN: 7tdDdkz987H3AYIWhNGXy8jTjJIoDhkAclCDLE26cTCj1KYX7EBHC66VEitJwWhn" -H "X-Requested-With: XMLHttpRequest" -F _method=PUT -F documents[]=@einvoice.xml
    }

    /**
     * @throws \Throwable
     */
    public function run(): \App\Models\Expense
    {

        //parse doc

        //append file as an attachment to the document


        /** @var \App\Models\Expense $expense */
        $expense = ExpenseFactory::create($this->company->id, $this->company->owner()->id);

        return $expense;
    }
}