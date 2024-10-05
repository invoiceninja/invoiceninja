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

namespace App\Http\Requests\ClientPortal\Uploads;

use Illuminate\Foundation\Http\FormRequest;

class StoreUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {

        /** @phpstan-ignore-next-line **/
        auth()->guard('contact')->user()->loadMissing(['client' => function ($query) {
            $query->without('gateway_tokens', 'documents', 'contacts.company', 'contacts'); // Exclude 'grandchildren' relation of 'client'
        }]);

        return (bool) auth()->guard('contact')->user()->client->getSetting('client_portal_enable_uploads');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'file' => ['file', 'mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000'],
        ];
    }

    /**
     * Since saveDocuments() expects an array of uploaded files,
     * we need to convert it to an array before uploading.
     *
     * @return mixed
     */
    public function getFile()
    {
        if (gettype($this->file) !== 'array') {
            return [$this->file];
        }

        return $this->file;
    }
}
