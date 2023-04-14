<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Invoice;

use App\Http\Requests\Request;
use Illuminate\Http\UploadedFile;

class UploadInvoiceRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->invoice);
    }

    public function rules()
    {
        $rules = [];

        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = $this->file_validation;
        } elseif ($this->file('documents')) {
            $rules['documents'] = $this->file_validation;
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->file_validation;
        } elseif ($this->file('file')) {
            $rules['file'] = $this->file_validation;
        }

        return $rules;
    }

    public function prepareForValidation()
    {

        //tests to see if upload via binary data works.
        
        // if(request()->getContent())
        // {
        //     // $file = new UploadedFile(request()->getContent(), request()->header('filename'));
        //     $file = new UploadedFile(request()->getContent(), 'something.png');
        //     // request()->files->set('documents', $file);
     
        //     $this->files->add(['file' => $file]);

        //     // Merge it in request also (As I found this is not needed in every case)
        //     $this->merge(['file' => $file]);


        // }
       


    }
}
