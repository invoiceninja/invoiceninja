<?php

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
        return (bool) auth('contact')->user()->client->getSetting('client_portal_enable_uploads');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'file' => ['file', 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000'],
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
