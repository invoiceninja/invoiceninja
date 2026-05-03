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

use App\Models\Document;
use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Http\FormRequest;

class DownloadMultipleDocumentsRequest extends FormRequest
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\ClientContact $contact */
        $contact = auth()->guard('contact')->user();

        $document_ids = $this->transformKeys($this->file_hash ?? []);

        $documents = Document::query()
            ->whereIn('id', $document_ids)
            ->where('company_id', $contact->company_id)
            ->get();

        // Fail if any requested document doesn't exist in this company
        if ($documents->count() !== count($document_ids)) {
            return false;
        }

        foreach ($documents as $document) {
            if (! $this->contactCanAccessDocument($contact, $document)) {
                return false;
            }
        }

        return true;
    }

    private function contactCanAccessDocument(ClientContact $contact, Document $document): bool
    {
        // Public company-level documents
        if ($document->is_public && $document->documentable_type == 'App\Models\Company') {
            return $document->company_id == $contact->company_id;
        }

        // Documents attached directly to a client
        if ($document->documentable_type == 'App\Models\Client') {
            return ClientContact::where('client_id', $document->documentable_id)
                                ->where('email', $contact->email)
                                ->where('company_id', $contact->company_id)
                                ->exists();
        }

        // Public documents on entities (Invoice, Quote, etc.) belonging to a client
        // this contact has access to
        if ($document->is_public
           && ($entity = $document->documentable)
           && isset($entity->client_id)) {
            return ClientContact::where('client_id', $entity->client_id)
                                ->where('email', $contact->email)
                                ->where('company_id', $contact->company_id)
                                ->exists();
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'file_hash' => ['required', 'array'],
        ];
    }
}
