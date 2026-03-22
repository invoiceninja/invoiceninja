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

namespace App\Http\Requests\ClientPortal\Documents;

use App\Models\Client;
use App\Models\Company;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Http\FormRequest;

class ShowDocumentRequest extends FormRequest
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $contact = auth()->guard('contact')->user();
        $document = $this->document;

        // Documents attached directly to this client
        if ($document->documentable_type === Client::class) {
            return $document->documentable_id == $contact->client_id;
        }

        // Public company-level documents
        if ($document->is_public && $document->documentable_type === Company::class) {
            return $document->company_id == $contact->company_id;
        }

        // Public documents on entities (Invoice, Quote, etc.) belonging to this client
        if ($document->is_public
            && ($entity = $document->documentable)
            && isset($entity->client_id)) {
            return $entity->client_id == $contact->client_id;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
        ];
    }
}
