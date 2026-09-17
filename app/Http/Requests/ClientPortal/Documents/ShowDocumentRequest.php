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

use App\Models\ClientContact;
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

        if (! $document->is_public) {
            return false;
        }

        // Public company-level documents
        if ($document->documentable_type == 'App\Models\Company') {
            return $document->company_id == $contact->company_id;
        }

        // Documents attached directly to a client.
        // Check by email rather than client_id so that contacts shared across multiple
        // clients in the same company (and client-switcher sessions) are handled correctly.
        if ($document->documentable_type == 'App\Models\Client') {
            return ClientContact::where('client_id', $document->documentable_id)
                                ->where('email', $contact->email)
                                ->where('company_id', $contact->company_id)
                                ->exists();
        }

        $entity = $document->documentable;

        if ($entity === null || ! isset($entity->client_id)) {
            return false;
        }

        // Public documents on entities (Invoice, Quote, etc.) belonging to a client
        // this contact has access to.
            return ClientContact::where('client_id', $entity->client_id)
                                ->where('email', $contact->email)
                                ->where('company_id', $contact->company_id)
                                ->exists();

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
